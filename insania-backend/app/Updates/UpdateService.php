<?php

namespace App\Updates;

use Illuminate\Support\Facades\Artisan;
use ZipArchive;

/**
 * Signed patches, applied from inside the system, with a backup so each one can be undone.
 *
 * A patch = a zip (manifest.json + files laid out as insania_app/... and public_html/...) and a signature file.
 * 1. inspect: signature, paths, PHP syntax, which files change.   Nothing is written.
 * 2. apply:   back up every file about to change, write the new ones, run database changes, clear caches.
 *             If anything fails, everything is put back automatically.
 * 3. undo:    put the backed up files back and roll the update's database changes back.
 * What a backup does NOT hold: the data in your database or uploaded documents. Those need their own backup.
 */
class UpdateService
{
    public function dir(string $sub = ''): string
    {
        $d = rtrim((string) config('updates.dir'), '/').($sub ? '/'.$sub : '');
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
        }

        return $d;
    }

    private function appPath(): string
    {
        return rtrim((string) config('updates.app_path', base_path()), '/');
    }

    public function currentBuild(): string
    {
        $f = $this->appPath().'/BUILD';

        return is_file($f) ? trim((string) file_get_contents($f)) : 'unknown';
    }

    /** Short id of the public key this server trusts, so the owner can match it with the key they hold. */
    public function keyFingerprint(): string
    {
        $k = openssl_pkey_get_public((string) config('updates.public_key'));
        $d = $k ? (openssl_pkey_get_details($k)['ec'] ?? null) : null;

        return $d ? strtoupper(substr(hash('sha256', "\x04".$d['x'].$d['y']), 0, 16)) : 'none';
    }

    // ---------------------------------------------------------------- history

    public function history(): array
    {
        $f = $this->dir().'/history.json';
        $a = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];

        return is_array($a) ? $a : [];
    }

    private function saveHistory(array $h): void
    {
        file_put_contents($this->dir().'/history.json', json_encode(array_values($h), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function latestApplied(): ?array
    {
        foreach (array_reverse($this->history()) as $e) {
            if (($e['status'] ?? '') === 'applied') {
                return $e;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- incoming files

    public function store(string $zipTmp, string $sigText): string
    {
        $id = bin2hex(random_bytes(12));
        $in = $this->dir('incoming');
        foreach (glob($in.'/*') ?: [] as $old) {           // forget uploads older than a day
            if (is_file($old) && filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        if (! @copy($zipTmp, "$in/$id.zip")) {
            throw new UpdateException('The uploaded file could not be saved. Check that insania_app/storage can be written.');
        }
        file_put_contents("$in/$id.sig", $sigText);

        return $id;
    }

    /**
     * One upload. Either a bundle (a zip holding patch.zip and signature.sig, made by tools/patch-signer.html)
     * or a plain patch zip together with its signature text.
     */
    public function storeUpload(string $tmp, ?string $sigText): string
    {
        $z = new ZipArchive;
        if ($z->open($tmp) === true) {
            $names = [];
            for ($i = 0; $i < $z->numFiles; $i++) {
                $names[] = (string) $z->getNameIndex($i);
            }
            if ($z->locateName('manifest.json') === false && in_array('patch.zip', $names, true) && in_array('signature.sig', $names, true) && count($names) === 2) {
                $patch = (string) $z->getFromName('patch.zip');
                $sig = (string) $z->getFromName('signature.sig');
                $z->close();
                if ($patch === '' || strlen($sig) > 4096) {
                    throw new UpdateException('The update bundle is not valid.');
                }
                $t = tempnam(sys_get_temp_dir(), 'bundle');
                file_put_contents($t, $patch);
                try {
                    return $this->store($t, $sig);
                } finally {
                    @unlink($t);
                }
            }
            $z->close();
        }
        if ($sigText === null || trim($sigText) === '') {
            throw new UpdateException('This patch has no signature. Sign it with patch-signer.html and upload the one-file update it makes, or add the .sig file.');
        }

        return $this->store($tmp, $sigText);
    }

    public function discard(string $id): void
    {
        if (preg_match('/^[a-f0-9]{24}$/', $id)) {
            @unlink($this->dir('incoming')."/$id.zip");
            @unlink($this->dir('incoming')."/$id.sig");
        }
    }

    // ---------------------------------------------------------------- inspect

    /** Reads and checks a stored patch. Writes nothing. @return array{summary: array, plan: array} */
    public function inspect(string $id): array
    {
        if (! preg_match('/^[a-f0-9]{24}$/', $id)) {
            throw new UpdateException('Unknown patch.');
        }
        $zipFile = $this->dir('incoming')."/$id.zip";
        $sigFile = $this->dir('incoming')."/$id.sig";
        if (! is_file($zipFile) || ! is_file($sigFile)) {
            throw new UpdateException('This upload is no longer available. Upload the files again.');
        }

        $bytes = (string) file_get_contents($zipFile);
        $this->verifySignature($bytes, (string) file_get_contents($sigFile));

        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== true) {
            throw new UpdateException('The patch file is not a readable zip.');
        }
        if ($zip->numFiles > (int) config('updates.max_files')) {
            throw new UpdateException('The patch has too many files.');
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest)) {
            throw new UpdateException('The patch has no readable manifest.json.');
        }
        foreach (['name', 'version', 'build'] as $k) {
            if (! isset($manifest[$k]) || ! is_string($manifest[$k]) || $manifest[$k] === '') {
                throw new UpdateException("The patch manifest is missing \"$k\".");
            }
        }
        if (! preg_match('/^[A-Za-z0-9._-]{1,40}$/', $manifest['build'])) {
            throw new UpdateException('The patch build id is not valid.');
        }
        $current = $this->currentBuild();
        if ($manifest['build'] === $current) {
            throw new UpdateException('This server already runs build '.$current.'.');
        }
        if (! empty($manifest['from_build']) && $manifest['from_build'] !== $current) {
            throw new UpdateException('This patch is for build '.$manifest['from_build'].' but this server is on build '.$current.'. Apply the earlier patch first.');
        }

        $plan = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === 'manifest.json' || str_ends_with($name, '/')) {
                continue;
            }
            [$root, $rel] = $this->split($name);
            $this->guardSymlink($zip, $i, $name);
            $content = (string) $zip->getFromIndex($i);
            $total += strlen($content);
            if ($total > (int) config('updates.max_bytes')) {
                throw new UpdateException('The patch is too large.');
            }
            if (str_ends_with($rel, '.php')) {
                try {
                    token_get_all($content, TOKEN_PARSE);
                } catch (\ParseError $e) {
                    throw new UpdateException("The file $name has a PHP error (line {$e->getLine()}), so the patch was refused. Nothing was changed.");
                }
            }
            $target = $this->target($root, $rel);
            $exists = is_file($target);
            $same = $exists && hash_equals(hash('sha256', (string) file_get_contents($target)), hash('sha256', $content));
            $plan[] = ['op' => $same ? 'same' : ($exists ? 'change' : 'add'), 'root' => $root, 'path' => $rel, 'target' => $target, 'zip_index' => $i];
        }
        foreach ((array) ($manifest['delete'] ?? []) as $name) {
            [$root, $rel] = $this->split((string) $name);
            $target = $this->target($root, $rel);
            if (is_file($target)) {
                $plan[] = ['op' => 'delete', 'root' => $root, 'path' => $rel, 'target' => $target];
            }
        }
        $zip->close();

        $unwritable = [];
        foreach ($plan as $p) {
            if ($p['op'] === 'same') {
                continue;
            }
            $probe = is_file($p['target']) ? $p['target'] : dirname($p['target']);
            while (! file_exists($probe) && $probe !== dirname($probe)) {
                $probe = dirname($probe);
            }
            if (! is_writable($probe)) {
                $unwritable[] = $p['root'].'/'.$p['path'];
            }
        }
        if ($unwritable) {
            throw new UpdateException('The server cannot write to: '.implode(', ', array_slice($unwritable, 0, 5)).'. Set those folders to permission 755 or 775 in File Manager.');
        }

        $count = fn (string $op) => count(array_filter($plan, fn ($p) => $p['op'] === $op));
        $migrations = array_values(array_map(fn ($p) => basename($p['path']), array_filter($plan, fn ($p) => $p['root'] === 'insania_app' && str_starts_with($p['path'], 'database/migrations/') && $p['op'] !== 'same')));

        return [
            'plan' => $plan,
            'manifest' => $manifest,
            'summary' => [
                'id' => $id, 'name' => $manifest['name'], 'version' => $manifest['version'], 'build' => $manifest['build'],
                'from_build' => $manifest['from_build'] ?? null, 'current_build' => $current, 'notes' => (string) ($manifest['notes'] ?? ''),
                'add' => $count('add'), 'change' => $count('change'), 'delete' => $count('delete'), 'unchanged' => $count('same'),
                'database_changes' => $migrations !== [],
                'files' => array_slice(array_map(fn ($p) => ['op' => $p['op'], 'path' => $p['root'].'/'.$p['path']], array_filter($plan, fn ($p) => $p['op'] !== 'same')), 0, 60),
            ],
        ];
    }

    private function verifySignature(string $bytes, string $sigText): void
    {
        $sig = base64_decode(preg_replace('/\s+/', '', $sigText), true);
        $key = openssl_pkey_get_public((string) config('updates.public_key'));
        if ($sig === false || $sig === '' || ! $key) {
            throw new UpdateException('The signature file is not valid. Patches must be signed with the owner\'s key.');
        }
        if (openssl_verify($bytes, $sig, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new UpdateException('The signature does not match this patch. It was not signed with the owner\'s key, or the file was changed. Nothing was done.');
        }
    }

    /** "insania_app/app/X.php" -> ['insania_app', 'app/X.php'], refusing anything outside the allowed places. */
    private function split(string $name): array
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#//#', $name)) {
            throw new UpdateException("The patch contains an unsafe path: $name");
        }
        $parts = explode('/', $name, 2);
        $root = $parts[0];
        $rel = $parts[1] ?? '';
        $allowed = config('updates.allowed');
        if (! isset($allowed[$root]) || $rel === '') {
            throw new UpdateException("The patch contains a file outside the allowed places: $name");
        }
        foreach ($allowed[$root] as $ok) {
            if ($rel === $ok || (str_ends_with($ok, '/') && str_starts_with($rel, $ok))) {
                if (preg_match('#(^|/)\.env#', $rel)) {
                    break;
                }

                return [$root, $rel];
            }
        }
        throw new UpdateException("A patch may not change this file: $name");
    }

    private function target(string $root, string $rel): string
    {
        $base = $root === 'insania_app' ? $this->appPath() : config('updates.public_path');
        if (! $base) {
            throw new UpdateException('The website folder (public_html) was not found next to insania_app, so website files cannot be updated.');
        }

        return rtrim($base, '/').'/'.$rel;
    }

    private function guardSymlink(ZipArchive $zip, int $i, string $name): void
    {
        $os = null;
        $attr = null;
        $zip->getExternalAttributesIndex($i, $os, $attr);
        if ($os === ZipArchive::OPSYS_UNIX && $attr && ((($attr >> 16) & 0170000) === 0120000)) {
            throw new UpdateException("The patch contains a link, which is not allowed: $name");
        }
    }

    // ---------------------------------------------------------------- apply

    public function apply(string $id, ?int $userId = null): array
    {
        $lock = fopen($this->dir().'/lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new UpdateException('Another update is running. Wait a minute and try again.');
        }

        try {
            $ins = $this->inspect($id);                       // verified again: nothing is trusted from the first check
            $m = $ins['manifest'];
            $plan = array_values(array_filter($ins['plan'], fn ($p) => $p['op'] !== 'same'));
            $bid = date('Ymd-His').'-'.substr($id, 0, 6);
            $bdir = $this->dir("backups/$bid");

            // 1. back up what is about to change
            $backup = ['changed' => [], 'added' => [], 'deleted' => []];
            foreach ($plan as $p) {
                if ($p['op'] === 'add') {
                    $backup['added'][] = ['root' => $p['root'], 'path' => $p['path']];

                    continue;
                }
                $dest = "$bdir/files/{$p['root']}/{$p['path']}";
                @mkdir(dirname($dest), 0775, true);
                if (! @copy($p['target'], $dest)) {
                    throw new UpdateException('The backup could not be written, so nothing was changed. Check insania_app/storage permissions and free space.');
                }
                $backup[$p['op'] === 'delete' ? 'deleted' : 'changed'][] = ['root' => $p['root'], 'path' => $p['path']];
            }
            $before = $this->ranMigrations();
            file_put_contents("$bdir/backup.json", json_encode(['from_build' => $this->currentBuild(), 'to_build' => $m['build']] + $backup, JSON_PRETTY_PRINT));

            // 2. write, 3. database, 4. caches. Any failure puts everything back.
            $zip = new ZipArchive;
            $zip->open($this->dir('incoming')."/$id.zip");
            try {
                foreach ($plan as $p) {
                    if ($p['op'] === 'delete') {
                        @unlink($p['target']);

                        continue;
                    }
                    $this->writeFile($p['target'], (string) $zip->getFromIndex($p['zip_index']));
                }
                $zip->close();
                $this->afterFiles();
                $added = array_values(array_diff($this->migrate(), $before));
            } catch (\Throwable $e) {
                $this->restore($bdir, $backup, $before);
                $this->rrmdir($bdir);                       // everything is back as it was; the backup is not needed
                $this->record(['id' => $bid, 'status' => 'failed', 'error' => $e->getMessage()] + $this->entry($m, $userId));
                throw new UpdateException('The update failed and was undone automatically: '.$e->getMessage());
            }

            $entry = ['id' => $bid, 'status' => 'applied', 'migrations' => $added,
                'counts' => ['add' => count($backup['added']), 'change' => count($backup['changed']), 'delete' => count($backup['deleted'])]] + $this->entry($m, $userId);
            $this->record($entry);
            $this->prune();
            $this->discard($id);

            return $entry;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function entry(array $m, ?int $userId): array
    {
        return ['at' => date('c'), 'name' => $m['name'], 'version' => $m['version'], 'build' => $m['build'], 'from_build' => $m['from_build'] ?? null, 'notes' => (string) ($m['notes'] ?? ''), 'by' => $userId];
    }

    private function record(array $e): void
    {
        $h = $this->history();
        $h[] = $e;
        $this->saveHistory($h);
    }

    private function writeFile(string $target, string $content): void
    {
        $d = dirname($target);
        if (! is_dir($d) && ! @mkdir($d, 0755, true)) {
            throw new \RuntimeException("cannot create folder $d");
        }
        $tmp = $target.'.new-'.bin2hex(random_bytes(3));
        if (file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException("cannot write $target");
        }
        @chmod($tmp, 0644);
        if (! @rename($tmp, $target)) {
            @unlink($tmp);
            throw new \RuntimeException("cannot replace $target");
        }
    }

    private function afterFiles(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        foreach (['config:clear', 'route:clear', 'view:clear'] as $c) {
            try {
                Artisan::call($c);
            } catch (\Throwable) {
            }
        }
    }

    private function ranMigrations(): array
    {
        $repo = app('migrator')->getRepository();

        return $repo->repositoryExists() ? $repo->getRan() : [];
    }

    private function migrate(): array
    {
        Artisan::call('migrate', ['--force' => true]);

        return $this->ranMigrations();
    }

    private function restore(string $bdir, array $backup, array $ranBefore): void
    {
        // database first (the update's own migrations), then files
        try {
            $newly = array_diff($this->ranMigrations(), $ranBefore);
            if ($newly) {
                Artisan::call('migrate:rollback', ['--force' => true]);
            }
        } catch (\Throwable) {
        }
        foreach (array_merge($backup['changed'], $backup['deleted']) as $f) {
            $this->writeFile($this->target($f['root'], $f['path']), (string) file_get_contents("$bdir/files/{$f['root']}/{$f['path']}"));
        }
        foreach ($backup['added'] as $f) {
            @unlink($this->target($f['root'], $f['path']));
        }
        $this->afterFiles();
    }

    private function prune(): void
    {
        $dirs = glob($this->dir().'/backups/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        foreach (array_slice($dirs, 0, max(0, count($dirs) - (int) config('updates.keep_backups'))) as $old) {
            $this->rrmdir($old);
        }
    }

    private function rrmdir(string $d): void
    {
        foreach (scandir($d) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            is_dir("$d/$f") ? $this->rrmdir("$d/$f") : @unlink("$d/$f");
        }
        @rmdir($d);
    }

    // ---------------------------------------------------------------- undo

    public function undo(string $bid, ?int $userId = null): array
    {
        $latest = $this->latestApplied();
        if (! $latest || $latest['id'] !== $bid) {
            throw new UpdateException('Only the most recent update can be undone. Undo that one first.');
        }
        $bdir = $this->dir("backups/$bid");
        if (! is_file("$bdir/backup.json")) {
            throw new UpdateException('The backup for this update is gone, so it cannot be undone.');
        }
        $lock = fopen($this->dir().'/lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new UpdateException('Another update is running. Wait a minute and try again.');
        }

        try {
            $backup = json_decode((string) file_get_contents("$bdir/backup.json"), true);
            // the migrations this update ran are rolled back by their own down() code
            $mig = $latest['migrations'] ?? [];
            if ($mig) {
                try {
                    Artisan::call('migrate:rollback', ['--force' => true]);
                } catch (\Throwable $e) {
                    throw new UpdateException('The database changes could not be rolled back: '.$e->getMessage().' Nothing was undone.');
                }
            }
            foreach (array_merge($backup['changed'], $backup['deleted']) as $f) {
                $this->writeFile($this->target($f['root'], $f['path']), (string) file_get_contents("$bdir/files/{$f['root']}/{$f['path']}"));
            }
            foreach ($backup['added'] as $f) {
                @unlink($this->target($f['root'], $f['path']));
            }
            $this->afterFiles();

            $h = $this->history();
            foreach ($h as &$e) {
                if ($e['id'] === $bid) {
                    $e['status'] = 'undone';
                    $e['undone_at'] = date('c');
                    $e['undone_by'] = $userId;
                }
            }
            unset($e);
            $this->saveHistory($h);

            return ['id' => $bid, 'restored_build' => $backup['from_build']];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
