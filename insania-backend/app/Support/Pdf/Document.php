<?php

namespace App\Support\Pdf;

/**
 * A PDF file opened for filling. The file itself is never edited: changed and new objects are kept on the side,
 * and save() writes a fresh copy with every object the document still uses (anything no longer used, such as an
 * old field picture, is left out). Content, fonts, pictures and scripts are copied byte for byte.
 */
final class Document
{
    /** @var array<int, array{0:int,1:int,2:int}> object number => [type 1: offset, gen | type 2: object stream, index] */
    private array $xref = [];

    private array $cache = [];

    private array $objStreams = [];

    /** @var array<int, mixed> objects changed or added (overlay) */
    private array $changed = [];

    public Dict $trailer;

    private int $next;

    private function __construct(private string $buf)
    {
    }

    public static function load(string $bytes): self
    {
        if (! str_starts_with(ltrim(substr($bytes, 0, 1024)), '%PDF-') && strpos(substr($bytes, 0, 1024), '%PDF-') === false) {
            throw new \RuntimeException('This file is not a PDF.');
        }
        $d = new self($bytes);
        try {
            $pos = strrpos($bytes, 'startxref');
            if ($pos === false) {
                throw new \RuntimeException('no startxref');
            }
            $p = new Parser($bytes, $pos + 9);
            $off = $p->value();
            $d->readXref((int) $off, 0);
        } catch (\Throwable $e) {
            $d->xref = [];
            $d->rebuild();
        }
        if (! $d->trailer->get('Root')) {
            throw new \RuntimeException('This PDF has no document catalog.');
        }
        if ($d->trailer->get('Encrypt')) {
            throw new \RuntimeException('This PDF is protected with a password. Ask for an unprotected copy of the form.');
        }
        $d->next = max(array_keys($d->xref) ?: [0]) + 1;

        return $d;
    }

    /** A separate copy for one filling: shares the read-only file and what was read from it, with its own changes. */
    public function fork(): self
    {
        $c = clone $this;
        $c->changed = [];
        $c->next = $this->next;

        return $c;
    }

    private function readXref(int $off, int $depth): void
    {
        if ($depth > 50) {
            return;
        }
        $p = new Parser($this->buf, $off);
        $p->skipWs();
        if (substr($this->buf, $p->p, 4) === 'xref') {
            $p->p += 4;
            while (true) {
                $p->skipWs();
                if (substr($this->buf, $p->p, 7) === 'trailer') {
                    $p->p += 7;
                    break;
                }
                $start = (int) $p->value();
                $count = (int) $p->value();
                for ($i = 0; $i < $count; $i++) {
                    $p->skipWs();
                    $line = substr($this->buf, $p->p, 20);
                    if (! preg_match('/^(\d{10}) (\d{5}) ([nf])/', $line, $m)) {
                        throw new \RuntimeException('bad xref line');
                    }
                    $p->p += 18;
                    $num = $start + $i;
                    if ($m[3] === 'n' && ! isset($this->xref[$num]) && $num > 0) {
                        $this->xref[$num] = [1, (int) $m[1], (int) $m[2]];
                    } elseif ($m[3] === 'f' && ! isset($this->xref[$num])) {
                        $this->xref[$num] = [0, 0, 0];
                    }
                }
            }
            $tr = $p->value();
            if (! $tr instanceof Dict) {
                throw new \RuntimeException('bad trailer');
            }
            $this->trailer ??= $tr;
            if (is_int($tr->get('XRefStm'))) {
                $this->readXref($tr->get('XRefStm'), $depth + 1);
            }
            if (is_int($tr->get('Prev'))) {
                $this->readXref($tr->get('Prev'), $depth + 1);
            }

            return;
        }
        // a cross-reference stream
        [$num, $obj] = $this->parseAt($off);
        if (! $obj instanceof Stream || $obj->dict->name('Type') !== 'XRef') {
            throw new \RuntimeException('bad xref stream');
        }
        $this->trailer ??= new Dict(array_diff_key($obj->dict->v, array_flip(['Type', 'Filter', 'DecodeParms', 'Length', 'W', 'Index'])));
        $w = $obj->dict->get('W');
        $size = (int) $obj->dict->get('Size');
        $index = $obj->dict->get('Index') ?: [0, $size];
        $data = $obj->decoded();
        $rowLen = array_sum($w);
        $pos = 0;
        for ($k = 0; $k + 1 < count($index); $k += 2) {
            for ($i = 0; $i < $index[$k + 1]; $i++) {
                if ($pos + $rowLen > strlen($data)) {
                    break 2;
                }
                $f = [];
                foreach ($w as $j => $width) {
                    $v = 0;
                    for ($b = 0; $b < $width; $b++) {
                        $v = ($v << 8) | ord($data[$pos++]);
                    }
                    $f[$j] = $width ? $v : ($j === 0 ? 1 : 0);
                }
                $n = $index[$k] + $i;
                if (isset($this->xref[$n]) || $n === 0) {
                    continue;
                }
                $this->xref[$n] = match ($f[0]) {
                    1 => [1, $f[1], $f[2]],
                    2 => [2, $f[1], $f[2]],
                    default => [0, 0, 0],
                };
            }
        }
        if (is_int($obj->dict->get('Prev'))) {
            $this->readXref($obj->dict->get('Prev'), $depth + 1);
        }
    }

    /** When the index at the end of the file is damaged: find every "N G obj" in the file. */
    private function rebuild(): void
    {
        preg_match_all('/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/', $this->buf, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[1] as $i => [$num, $off]) {
            $this->xref[(int) $num] = [1, (int) $m[0][$i][1], (int) $m[2][$i][0]];
        }
        $trailer = null;
        if (preg_match_all('/trailer\s*<</', $this->buf, $t, PREG_OFFSET_CAPTURE)) {
            $p = new Parser($this->buf, end($t[0])[1] + 7);
            $trailer = $p->value();
        }
        if (! $trailer instanceof Dict || ! $trailer->get('Root')) {
            foreach ($this->xref as $num => $e) {
                try {
                    $o = $this->get($num);
                } catch (\Throwable) {
                    continue;
                }
                if ($o instanceof Stream && $o->dict->name('Type') === 'XRef' && $o->dict->get('Root')) {
                    $trailer = new Dict(['Root' => $o->dict->get('Root'), 'Info' => $o->dict->get('Info'), 'ID' => $o->dict->get('ID')]);
                }
                if ($o instanceof Dict && $o->name('Type') === 'Catalog') {
                    $trailer = new Dict(['Root' => new Ref($num)]);
                }
            }
        }
        $this->trailer = $trailer instanceof Dict ? $trailer : new Dict();
        // objects inside object streams
        foreach (array_keys($this->xref) as $num) {
            try {
                $o = $this->get($num);
            } catch (\Throwable) {
                continue;
            }
            if ($o instanceof Stream && $o->dict->name('Type') === 'ObjStm') {
                foreach ($this->objStream($num) as $inner => $_) {
                    $this->xref[$inner] ??= [2, $num, 0];
                }
            }
        }
        $this->cache = [];
    }

    /** @return array{0:int, 1:mixed} */
    private function parseAt(int $off): array
    {
        $p = new Parser($this->buf, $off);
        $num = $p->value();
        $p->value();
        if ($p->keyword() !== 'obj') {
            throw new \RuntimeException('No object at '.$off);
        }
        $v = $p->value();
        $save = $p->p;
        if ($v instanceof Dict && $p->keyword() === 'stream') {
            if (substr($this->buf, $p->p, 2) === "\r\n") {
                $p->p += 2;
            } elseif (($this->buf[$p->p] ?? '') === "\n" || ($this->buf[$p->p] ?? '') === "\r") {
                $p->p++;
            }
            $len = $v->get('Length');
            if ($len instanceof Ref) {
                $len = $this->get($len->num);
            }
            $start = $p->p;
            $ok = is_int($len) && $len >= 0 && preg_match('/^\s*endstream/', substr($this->buf, $start + $len, 32));
            if (! $ok) {
                $e = strpos($this->buf, 'endstream', $start);
                $len = ($e === false ? strlen($this->buf) : $e) - $start;
                $tail = substr($this->buf, $start, $len);
                $len = strlen(rtrim($tail, "\r\n"));
            }

            return [(int) $num, new Stream($v, substr($this->buf, $start, $len))];
        }
        $p->p = $save;

        return [(int) $num, $v];
    }

    private function objStream(int $num): array
    {
        if (isset($this->objStreams[$num])) {
            return $this->objStreams[$num];
        }
        $s = $this->get($num);
        if (! $s instanceof Stream) {
            return $this->objStreams[$num] = [];
        }
        $data = $s->decoded();
        $n = (int) $s->dict->get('N');
        $first = (int) $s->dict->get('First');
        $p = new Parser($data);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $objNum = $p->value();
            $rel = $p->value();
            if (! is_int($objNum) || ! is_int($rel)) {
                break;
            }
            $out[$objNum] = [$i, $first + $rel];
        }
        $this->objStreams[$num] = ['__data' => $data] + $out;

        return $this->objStreams[$num];
    }

    /** The object with this number (changed version first), or null when it does not exist. */
    public function get(int $num): mixed
    {
        if (array_key_exists($num, $this->changed)) {
            return $this->changed[$num];
        }
        if (array_key_exists($num, $this->cache)) {
            return $this->cache[$num];
        }
        $e = $this->xref[$num] ?? null;
        $v = null;
        if ($e && $e[0] === 1) {
            [, $v] = $this->parseAt($e[1]);
        } elseif ($e && $e[0] === 2) {
            $os = $this->objStream($e[1]);
            if (isset($os[$num])) {
                $p = new Parser($os['__data'], $os[$num][1]);
                $v = $p->value();
            }
        }

        return $this->cache[$num] = $v;
    }

    /** Follows a reference; anything else is returned as it is. */
    public function resolve(mixed $v): mixed
    {
        $guard = 0;
        while ($v instanceof Ref && $guard++ < 32) {
            $v = $this->get($v->num);
        }

        return $v;
    }

    public function set(int $num, mixed $obj): void
    {
        $this->changed[$num] = $obj;
    }

    public function add(mixed $obj): Ref
    {
        $n = $this->next++;
        $this->changed[$n] = $obj;

        return new Ref($n);
    }

    public function root(): Dict
    {
        $r = $this->resolve($this->trailer->get('Root'));

        return $r instanceof Dict ? $r : new Dict();
    }

    /** Writes the document: every object still used, numbered again from 1, with a plain cross-reference table. */
    public function save(): string
    {
        $map = [];
        $order = [];
        $stack = [];
        $seed = [$this->trailer->get('Root'), $this->trailer->get('Info')];
        foreach ($seed as $s) {
            if ($s instanceof Ref) {
                $stack[] = $s->num;
            }
        }
        // walk everything reachable (no recursion: page trees point back to their parents)
        $stack = array_reverse($stack);
        while ($stack) {
            $num = array_pop($stack);
            if (isset($map[$num])) {
                continue;
            }
            $obj = $this->get($num);
            if ($obj === null) {
                continue;
            }
            $map[$num] = count($order) + 1;
            $order[] = $num;
            $refs = [];
            self::refsIn($obj instanceof Stream ? $obj->dict : $obj, $refs);
            foreach (array_reverse($refs) as $r) {
                if (! isset($map[$r])) {
                    $stack[] = $r;
                }
            }
        }
        $head = preg_match('/%PDF-(\d\.\d)/', substr($this->buf, 0, 1024), $m) ? $m[1] : '1.6';
        $out = "%PDF-{$head}\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($order as $num) {
            $offsets[] = strlen($out);
            $obj = $this->get($num);
            $out .= $map[$num]." 0 obj\n";
            if ($obj instanceof Stream) {
                $d = Dict::copy($obj->dict);
                $d->v['Length'] = strlen($obj->data);
                $out .= self::ser($d, $map)."\nstream\n".$obj->data."\nendstream";
            } else {
                $out .= self::ser($obj, $map);
            }
            $out .= "\nendobj\n";
        }
        $xrefAt = strlen($out);
        $n = count($order) + 1;
        $out .= "xref\n0 {$n}\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $id = $this->trailer->get('ID');
        $first = is_array($id) && ($id[0] ?? null) instanceof Str ? $id[0]->v : random_bytes(16);
        $tr = new Dict(['Size' => $n, 'Root' => $this->trailer->get('Root')]);
        if ($this->trailer->get('Info') instanceof Ref && isset($map[$this->trailer->get('Info')->num])) {
            $tr->v['Info'] = $this->trailer->get('Info');
        }
        $tr->v['ID'] = [new Str($first, true), new Str(md5($out, true), true)];

        return $out."trailer\n".self::ser($tr, $map)."\nstartxref\n{$xrefAt}\n%%EOF\n";
    }

    private static function refsIn(mixed $v, array &$out): void
    {
        if ($v instanceof Ref) {
            $out[] = $v->num;
        } elseif ($v instanceof Dict) {
            foreach ($v->v as $x) {
                self::refsIn($x, $out);
            }
        } elseif (is_array($v)) {
            foreach ($v as $x) {
                self::refsIn($x, $out);
            }
        }
    }

    /** One value written the PDF way. $map renumbers references (a reference to nothing becomes null). */
    public static function ser(mixed $v, array $map = []): string
    {
        if ($v === null) {
            return 'null';
        }
        if ($v === true || $v === false) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');

            return $s === '-0' || $s === '' ? '0' : $s;
        }
        if ($v instanceof Name) {
            return '/'.preg_replace_callback('/[^\x21-\x7e]|[#()<>\[\]{}\/%]/', fn ($m) => sprintf('#%02X', ord($m[0])), $v->v);
        }
        if ($v instanceof Str) {
            if ($v->hex) {
                return '<'.bin2hex($v->v).'>';
            }

            return '('.strtr($v->v, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r']).')';
        }
        if ($v instanceof Ref) {
            if ($map === []) {
                return $v->num.' '.$v->gen.' R';
            }

            return isset($map[$v->num]) ? $map[$v->num].' 0 R' : 'null';
        }
        if ($v instanceof Dict) {
            $s = '<<';
            foreach ($v->v as $k => $x) {
                if ($x === null) {
                    continue;
                }
                $s .= self::ser(new Name((string) $k)).' '.self::ser($x, $map).' ';
            }

            return rtrim($s).'>>';
        }
        if (is_array($v)) {
            return '['.implode(' ', array_map(fn ($x) => self::ser($x, $map), $v)).']';
        }
        if ($v instanceof Stream) {
            throw new \LogicException('A stream must be its own object.');
        }

        return 'null';
    }

    public static function num(float|int $f): string
    {
        if (is_int($f)) {
            return (string) $f;
        }
        $s = rtrim(rtrim(sprintf('%.4F', $f), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }
}
