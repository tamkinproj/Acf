<?php

namespace App\Support\Pdf;

/** A PDF stream: its dictionary and its bytes exactly as stored in the file (still compressed). */
final class Stream
{
    public function __construct(public Dict $dict, public string $data)
    {
    }

    /** A new stream, compressed. */
    public static function make(array $dict, string $plain, bool $compress = true): self
    {
        $d = new Dict($dict);
        if ($compress && strlen($plain) > 64) {
            $d->set('Filter', new Name('FlateDecode'));

            return new self($d, (string) gzcompress($plain, 6));
        }

        return new self($d, $plain);
    }

    /** The bytes after the stream's filters are undone (FlateDecode with predictors, ASCIIHex, ASCII85). */
    public function decoded(): string
    {
        $data = $this->data;
        $filters = $this->dict->get('Filter');
        $parms = $this->dict->get('DecodeParms');
        $filters = $filters instanceof Name ? [$filters] : (is_array($filters) ? $filters : []);
        $parms = is_array($parms) ? $parms : [$parms];
        foreach ($filters as $i => $f) {
            $p = $parms[$i] ?? null;
            $name = $f instanceof Name ? $f->v : '';
            $data = match ($name) {
                'FlateDecode', 'Fl' => self::predict(self::inflate($data), $p instanceof Dict ? $p : null),
                'ASCIIHexDecode', 'AHx' => (string) hex2bin(strlen($h = preg_replace('/[^0-9A-Fa-f]/', '', explode('>', $data)[0])) % 2 ? $h.'0' : $h),
                'ASCII85Decode', 'A85' => self::a85($data),
                default => throw new \RuntimeException('This PDF uses a compression this system cannot read ('.$name.').'),
            };
        }

        return $data;
    }

    private static function inflate(string $d): string
    {
        $out = @gzuncompress($d);
        if ($out !== false) {
            return $out;
        }
        // damaged or with extra bytes at the end: take what can be read
        $ctx = inflate_init(ZLIB_ENCODING_DEFLATE);
        $out = @inflate_add($ctx, $d, ZLIB_SYNC_FLUSH);
        if ($out === false || $out === '') {
            $ctx = inflate_init(ZLIB_ENCODING_RAW);
            $out = @inflate_add($ctx, substr($d, 2), ZLIB_SYNC_FLUSH);
        }

        return (string) $out;
    }

    private static function predict(string $data, ?Dict $p): string
    {
        $pred = $p ? (int) $p->get('Predictor') : 1;
        if ($pred < 10) {
            return $data;   // 1: none (2, TIFF, is not used by forms)
        }
        $colors = $p->get('Colors') ? (int) $p->get('Colors') : 1;
        $bpc = $p->get('BitsPerComponent') ? (int) $p->get('BitsPerComponent') : 8;
        $cols = $p->get('Columns') ? (int) $p->get('Columns') : 1;
        $bpp = max(1, intdiv($colors * $bpc + 7, 8));
        $row = intdiv($colors * $bpc * $cols + 7, 8);
        $out = '';
        $prev = str_repeat("\0", $row);
        $n = strlen($data);
        for ($i = 0; $i < $n; $i += $row + 1) {
            $type = ord($data[$i]);
            $line = substr($data, $i + 1, $row);
            $line = str_pad($line, $row, "\0");
            $cur = '';
            for ($x = 0; $x < $row; $x++) {
                $raw = ord($line[$x]);
                $a = $x >= $bpp ? ord($cur[$x - $bpp]) : 0;
                $b = ord($prev[$x]);
                $c = $x >= $bpp ? ord($prev[$x - $bpp]) : 0;
                $v = match ($type) {
                    1 => $raw + $a,
                    2 => $raw + $b,
                    3 => $raw + intdiv($a + $b, 2),
                    4 => $raw + self::paeth($a, $b, $c),
                    default => $raw,
                };
                $cur .= chr($v & 0xFF);
            }
            $out .= $cur;
            $prev = $cur;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    private static function a85(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        if (str_starts_with($s, '<~')) {
            $s = substr($s, 2);
        }
        $s = explode('~>', $s)[0];
        $out = '';
        $buf = [];
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            if ($s[$i] === 'z' && ! $buf) {
                $out .= "\0\0\0\0";

                continue;
            }
            $buf[] = ord($s[$i]) - 33;
            if (count($buf) === 5) {
                $v = 0;
                foreach ($buf as $d) {
                    $v = $v * 85 + $d;
                }
                $out .= pack('N', $v);
                $buf = [];
            }
        }
        if ($buf) {
            $k = count($buf);
            while (count($buf) < 5) {
                $buf[] = 84;
            }
            $v = 0;
            foreach ($buf as $d) {
                $v = $v * 85 + $d;
            }
            $out .= substr(pack('N', $v), 0, $k - 1);
        }

        return $out;
    }
}
