<?php

namespace App\Services;

use App\Http\Middleware\TrustProxies;
use App\Models\Setting;
use App\Models\User;
use App\Support\TrustedDevices;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who reaches the server, for the System Admin's Security page, and the list of blocked addresses.
 *
 * Light on purpose (shared hosting): every request adds one to its address's row for the hour and one to its page's row for
 * the hour. Only requests worth a look get a detail row (blocked, sign-in, failed sign-in, locked, too many requests, refused,
 * not found), at most EVENT_CAP per address per hour so nobody can fill the database. Everything older than the retention
 * period is deleted, once an hour at most. Recording never breaks a request: any failure is ignored.
 */
final class Traffic
{
    public const KEY = 'traffic_settings';
    public const EVENT_CAP = 60;
    public const KINDS = ['blocked', 'login', 'login_failed', 'login_locked', 'throttled', 'denied', 'not_found'];
    public const DEFAULTS = ['logging' => true, 'blocking' => true, 'retention_days' => 30, 'alerts' => true, 'alert_failed' => 10, 'alert_refused' => 50];
    public const RETENTION = [7, 90];
    public const ALERT_FAILED = [5, 10, 20, 50];       // wrong passwords from one address in an hour
    public const ALERT_REFUSED = [20, 50, 100, 200];   // refused, not found, too many requests from one address in an hour
    public const REFUSED_KINDS = ['login_locked', 'throttled', 'denied', 'not_found'];

    /** @return array{logging: bool, blocking: bool, retention_days: int} */
    public static function settings(): array
    {
        return Cache::remember('traffic-settings', 30, function () {
            $saved = (array) json_decode((string) Setting::withoutGlobalScopes()->where('key', self::KEY)->orderBy('id')->value('value'), true);

            $pick = fn (string $k, array $allowed) => in_array((int) ($saved[$k] ?? 0), $allowed, true) ? (int) $saved[$k] : self::DEFAULTS[$k];

            return [
                'logging' => (bool) ($saved['logging'] ?? self::DEFAULTS['logging']),
                'blocking' => (bool) ($saved['blocking'] ?? self::DEFAULTS['blocking']),
                'retention_days' => max(self::RETENTION[0], min(self::RETENTION[1], (int) ($saved['retention_days'] ?? self::DEFAULTS['retention_days']))),
                'alerts' => (bool) ($saved['alerts'] ?? self::DEFAULTS['alerts']),
                'alert_failed' => $pick('alert_failed', self::ALERT_FAILED),
                'alert_refused' => $pick('alert_refused', self::ALERT_REFUSED),
            ];
        });
    }

    public static function saveSettings(int $foundationId, array $values): array
    {
        $merged = array_merge(self::settings(), array_intersect_key($values, self::DEFAULTS));
        Setting::withoutGlobalScopes()->updateOrCreate(['foundation_id' => $foundationId, 'key' => self::KEY], ['value' => json_encode($merged)]);
        Cache::forget('traffic-settings');

        return self::settings();
    }

    /** The same address written one way ("2001:db8::1", not "2001:0db8:0:0::1"). */
    public static function canon(string $ip): string
    {
        $bin = @inet_pton(trim($ip));

        return $bin === false ? trim($ip) : (string) inet_ntop($bin);
    }

    public static function isCloudflare(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false && IpUtils::checkIp($ip, TrustProxies::CLOUDFLARE);
    }

    /** @return array<string, int|null> address => when the block ends (null: until unblocked) */
    public static function blocks(): array
    {
        return Cache::remember('blocked-ips', 60, fn () => DB::table('blocked_ips')->get(['ip', 'expires_at'])
            ->mapWithKeys(fn ($r) => [$r->ip => $r->expires_at ? Carbon::parse($r->expires_at)->getTimestamp() : null])->all());
    }

    public static function forgetBlocks(): void
    {
        Cache::forget('blocked-ips');
    }

    /** Is this request from a blocked address? The System Admin's own trusted device is never locked out, so a block can always be undone. */
    public static function refused(Request $request): bool
    {
        if (! self::settings()['blocking']) {
            return false;
        }
        $blocks = self::blocks();
        $ip = self::canon((string) $request->ip());
        if (! array_key_exists($ip, $blocks)) {
            return false;
        }
        if ($blocks[$ip] !== null && $blocks[$ip] <= now()->getTimestamp()) {
            return false;   // the block has ended
        }
        $device = TrustedDevices::device($request);
        if ($device && User::query()->whereKey($device->user_id)->value('role') === 'system_admin') {
            return false;
        }

        return true;
    }

    /** The visitor's country, from Cloudflare, only when the request really came through Cloudflare. */
    public static function country(Request $request): ?string
    {
        if (! $request->attributes->get('via_cloudflare')) {
            return null;
        }
        $c = strtoupper(substr((string) $request->headers->get('CF-IPCountry', ''), 0, 2));

        return preg_match('/^[A-Z]{2}$/', $c) && ! in_array($c, ['XX', 'T1'], true) ? $c : null;
    }

    /** The page, with numbers and long keys replaced, so "messages of chat 12" and "of chat 13" count together. */
    public static function path(Request $request): string
    {
        $p = '/'.ltrim((string) $request->path(), '/');
        $p = (string) preg_replace('#[^\x21-\x7E]#', '', $p);
        $p = (string) preg_replace('#/\d+(?=/|$)#', '/{n}', $p);
        $p = (string) preg_replace('#/[A-Za-z0-9_\-]{24,}(?=/|$)#', '/{key}', $p);

        return substr($p, 0, 160);
    }

    /** What kind of request this was, when it is worth a detail row. */
    public static function kind(Request $request, Response $response, bool $signedIn): ?string
    {
        if ($request->attributes->get('traffic_blocked')) {
            return 'blocked';
        }
        $status = $response->getStatusCode();
        if ($request->isMethod('POST') && $request->is('api/login')) {
            return match (true) {
                $status === 200 => 'login',
                $status === 429 => 'login_locked',
                $status === 401 => 'login_failed',
                default => null,
            };
        }

        return match (true) {
            $status === 429 => 'throttled',
            $status === 403 => 'denied',
            $status === 404 && ! $signedIn => 'not_found',
            default => null,
        };
    }

    /** Count this request (called after the answer has been sent). */
    public static function record(Request $request, Response $response): void
    {
        if (! self::settings()['logging'] || $request->isMethod('OPTIONS')) {
            return;
        }
        $ip = self::canon((string) $request->ip());
        if ($ip === '') {
            return;
        }
        $user = null;
        try {
            $user = $request->user();
        } catch (\Throwable) {
        }
        $kind = self::kind($request, $response, (bool) $user);
        $userId = $user?->id;
        if ($kind === 'login') {
            $userId = (int) (json_decode((string) $response->getContent(), true)['data']['user']['id'] ?? 0) ?: null;
        }
        $now = now();
        $hour = $now->copy()->startOfHour()->format('Y-m-d H:i:s');
        $at = $now->format('Y-m-d H:i:s');
        $country = self::country($request);
        $path = self::path($request);
        $ua = substr((string) $request->userAgent(), 0, 200) ?: null;

        $add = ['signins' => $kind === 'login', 'failed_logins' => $kind === 'login_failed', 'blocked' => $kind === 'blocked', 'throttled' => in_array($kind, ['throttled', 'login_locked'], true),
            'refused' => in_array($kind, self::REFUSED_KINDS, true)];
        $set = ['requests' => DB::raw('requests + 1'), 'last_at' => $at, 'last_path' => $path, 'user_agent' => $ua];
        foreach ($add as $col => $yes) {
            if ($yes) {
                $set[$col] = DB::raw("$col + 1");
            }
        }
        if ($userId) {
            $set['user_id'] = $userId;
        }
        if ($country) {
            $set['country'] = $country;
        }
        $row = fn () => DB::table('traffic_hits')->where('hour', $hour)->where('ip', $ip);
        if (! $row()->update($set)) {
            try {
                DB::table('traffic_hits')->insert(['hour' => $hour, 'ip' => $ip, 'country' => $country, 'requests' => 1, 'user_id' => $userId, 'user_agent' => $ua, 'last_path' => $path,
                    'first_at' => $at, 'last_at' => $at] + array_map(fn ($yes) => $yes ? 1 : 0, $add));
            } catch (QueryException) {
                $row()->update($set);   // another request made the row at the same moment
            }
        }

        $p = fn () => DB::table('traffic_paths')->where('hour', $hour)->where('path', $path);
        if (! $p()->update(['requests' => DB::raw('requests + 1')])) {
            try {
                DB::table('traffic_paths')->insert(['hour' => $hour, 'path' => $path, 'requests' => 1]);
            } catch (QueryException) {
                $p()->update(['requests' => DB::raw('requests + 1')]);
            }
        }

        // the detail row, at most EVENT_CAP per address per hour
        if ($kind && $row()->where('events', '<', self::EVENT_CAP)->update(['events' => DB::raw('events + 1')])) {
            DB::table('traffic_events')->insert(['at' => $at, 'ip' => $ip, 'country' => $country, 'kind' => $kind, 'method' => substr($request->method(), 0, 8),
                'path' => $path, 'status' => $response->getStatusCode(), 'user_id' => $userId, 'user_agent' => $ua]);
        }

        if ($kind === 'login_failed' || in_array($kind, self::REFUSED_KINDS, true)) {
            self::alert($ip, $kind, $row()->first(['failed_logins', 'refused', 'country']), $hour);
        }
    }

    /**
     * Tells the System Admin (on the bell) when one address reaches the wrong-password or refused threshold in an hour.
     * Once per address, kind and hour. It never blocks anything: the System Admin decides.
     */
    private static function alert(string $ip, string $kind, ?object $hit, string $hour): void
    {
        $s = self::settings();
        if (! $s['alerts'] || ! $hit) {
            return;
        }
        $failed = $kind === 'login_failed';
        $n = (int) ($failed ? $hit->failed_logins : $hit->refused);
        if ($n !== ($failed ? $s['alert_failed'] : $s['alert_refused'])) {
            return;
        }
        $where = $ip.($hit->country ? ' ('.$hit->country.')' : '');
        $title = $failed ? 'Many wrong passwords from one address' : 'Many refused requests from one address';
        $body = $failed ? "$where: $n wrong passwords this hour. Open Security to look at it or block it." : "$where: $n refused or unknown requests this hour. Open Security to look at it or block it.";
        foreach (User::withoutGlobalScopes()->where('role', 'system_admin')->where('active', true)->get(['id', 'foundation_id']) as $u) {
            \App\Support\Notify::to((int) $u->id, (int) $u->foundation_id, 'security', $title, $body, 'security.html?ip='.rawurlencode($ip), 'sec:'.($failed ? 'f' : 'r').':'.$ip.':'.substr($hour, 0, 13));
        }
    }

    /**
     * Upkeep, at most every ten minutes (after an answer has been sent): delete what is older than the retention period,
     * and remove at Cloudflare the blocks that have ended or were unblocked (Cloudflare's rules do not end by themselves).
     */
    public static function maintain(): void
    {
        if (! Cache::add('traffic-maintained', 1, 600)) {
            return;
        }
        self::prune();
        $due = DB::table('blocked_ips')->whereNotNull('cf_rule_id')->whereNotNull('expires_at')->where('expires_at', '<=', now()->format('Y-m-d H:i:s'))->limit(20)->get();
        if ($due->isEmpty() || ! ($cf = Cloudflare::saved())) {
            return;
        }
        foreach ($due as $b) {
            $r = $cf->unblockIp($b->cf_rule_id);
            if ($r['ok'] && $b->cf_status === 'removing') {
                DB::table('blocked_ips')->where('id', $b->id)->delete();   // unblocked by hand earlier: now gone from Cloudflare too
            } elseif ($r['ok']) {
                DB::table('blocked_ips')->where('id', $b->id)->update(['cf_rule_id' => null, 'cf_status' => 'removed', 'cf_error' => null, 'updated_at' => now()]);
            } else {
                DB::table('blocked_ips')->where('id', $b->id)->update(['cf_error' => mb_substr((string) $r['error'], 0, 200)]);
            }
        }
        self::forgetBlocks();
    }

    /** Deletes what is older than the retention period, and blocks that ended that long ago. */
    public static function prune(): void
    {
        $cut = now()->subDays(self::settings()['retention_days'])->format('Y-m-d H:i:s');
        DB::table('traffic_hits')->where('hour', '<', $cut)->delete();
        DB::table('traffic_paths')->where('hour', '<', $cut)->delete();
        DB::table('traffic_events')->where('at', '<', $cut)->delete();
        if (DB::table('blocked_ips')->whereNotNull('expires_at')->where('expires_at', '<', $cut)->whereNull('cf_rule_id')->delete()) {
            self::forgetBlocks();
        }
    }
}
