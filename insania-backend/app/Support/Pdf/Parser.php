<?php

namespace App\Support\Pdf;

/** Reads PDF objects from bytes. Numbers become int/float, the rest the small classes of this folder. */
final class Parser
{
    private const WS = "\x00\t\n\x0c\r ";
    private const DELIM = "()<>[]{}/%";

    public int $len;

    public function __construct(public string $s, public int $p = 0)
    {
        $this->len = strlen($s);
    }

    public function skipWs(): void
    {
        while ($this->p < $this->len) {
            $c = $this->s[$this->p];
            if (strpos(self::WS, $c) !== false) {
                $this->p++;
            } elseif ($c === '%') {
                while ($this->p < $this->len && $this->s[$this->p] !== "\n" && $this->s[$this->p] !== "\r") {
                    $this->p++;
                }
            } else {
                break;
            }
        }
    }

    /** A bare word (obj, endobj, stream, R, true ...), or null when the next thing is not one. */
    public function keyword(): ?string
    {
        $this->skipWs();
        $start = $this->p;
        while ($this->p < $this->len && strpos(self::WS.self::DELIM, $this->s[$this->p]) === false) {
            $this->p++;
        }

        return $this->p > $start ? substr($this->s, $start, $this->p - $start) : null;
    }

    public function value(): mixed
    {
        $this->skipWs();
        if ($this->p >= $this->len) {
            throw new \RuntimeException('The PDF ended too early.');
        }
        $c = $this->s[$this->p];
        if ($c === '/') {
            return $this->name();
        }
        if ($c === '(') {
            return $this->literal();
        }
        if ($c === '<') {
            if (($this->s[$this->p + 1] ?? '') === '<') {
                $this->p += 2;
                $d = new Dict();
                while (true) {
                    $this->skipWs();
                    if ($this->p >= $this->len) {
                        throw new \RuntimeException('A dictionary in the PDF is not closed.');
                    }
                    if ($this->s[$this->p] === '>' && ($this->s[$this->p + 1] ?? '') === '>') {
                        $this->p += 2;
                        break;
                    }
                    $k = $this->value();
                    if (! $k instanceof Name) {
                        throw new \RuntimeException('A dictionary key in the PDF is not a name.');
                    }
                    $this->skipWs();
                    if ($this->s[$this->p] === '>' && ($this->s[$this->p + 1] ?? '') === '>') {
                        $d->v[$k->v] = null;   // a key without a value: treat as absent
                        $this->p += 2;
                        break;
                    }
                    $d->v[$k->v] = $this->value();
                }

                return $d;
            }
            $end = strpos($this->s, '>', $this->p);
            if ($end === false) {
                throw new \RuntimeException('A hex string in the PDF is not closed.');
            }
            $h = preg_replace('/[^0-9A-Fa-f]/', '', substr($this->s, $this->p + 1, $end - $this->p - 1));
            $this->p = $end + 1;
            if (strlen($h) % 2) {
                $h .= '0';
            }

            return new Str((string) hex2bin($h), true);
        }
        if ($c === '[') {
            $this->p++;
            $a = [];
            while (true) {
                $this->skipWs();
                if ($this->p >= $this->len) {
                    throw new \RuntimeException('An array in the PDF is not closed.');
                }
                if ($this->s[$this->p] === ']') {
                    $this->p++;
                    break;
                }
                $a[] = $this->value();
            }

            return $a;
        }
        if (ctype_digit($c) || $c === '-' || $c === '+' || $c === '.') {
            $num = $this->number();
            if (is_int($num) && $num >= 0) {   // maybe "12 0 R"
                $save = $this->p;
                $this->skipWs();
                if ($this->p < $this->len && ctype_digit($this->s[$this->p])) {
                    $gen = $this->number();
                    $this->skipWs();
                    if (is_int($gen) && ($this->s[$this->p] ?? '') === 'R' && strpos(self::WS.self::DELIM, $this->s[$this->p + 1] ?? ' ') !== false) {
                        $this->p++;

                        return new Ref($num, $gen);
                    }
                }
                $this->p = $save;
            }

            return $num;
        }
        $w = $this->keyword();

        return match ($w) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw new \RuntimeException('Unexpected "'.substr((string) $w, 0, 20).'" in the PDF.'),
        };
    }

    private function number(): int|float
    {
        $start = $this->p;
        while ($this->p < $this->len && strpos('0123456789+-.', $this->s[$this->p]) !== false) {
            $this->p++;
        }
        $t = substr($this->s, $start, $this->p - $start);
        if (preg_match('/^[+-]?\d+$/', $t)) {
            return (int) $t;
        }
        if (! is_numeric($t)) {
            $t = preg_replace('/(?<=.)[+-]/', '', $t);   // "0.-5" style mistakes some programs write
        }

        return (float) $t;
    }

    private function name(): Name
    {
        $this->p++;
        $start = $this->p;
        while ($this->p < $this->len && strpos(self::WS.self::DELIM, $this->s[$this->p]) === false) {
            $this->p++;
        }
        $raw = substr($this->s, $start, $this->p - $start);
        if (str_contains($raw, '#')) {
            $raw = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $raw);
        }

        return new Name($raw);
    }

    private function literal(): Str
    {
        $this->p++;
        $out = '';
        $depth = 1;
        while ($this->p < $this->len) {
            $c = $this->s[$this->p++];
            if ($c === '\\') {
                $n = $this->s[$this->p++] ?? '';
                switch ($n) {
                    case 'n': $out .= "\n"; break;
                    case 'r': $out .= "\r"; break;
                    case 't': $out .= "\t"; break;
                    case 'b': $out .= "\x08"; break;
                    case 'f': $out .= "\x0c"; break;
                    case "\r":
                        if (($this->s[$this->p] ?? '') === "\n") {
                            $this->p++;
                        }
                        break;
                    case "\n": break;
                    default:
                        if ($n !== '' && $n >= '0' && $n <= '7') {
                            $oct = $n;
                            for ($k = 0; $k < 2 && ($this->s[$this->p] ?? '') >= '0' && ($this->s[$this->p] ?? '') <= '7'; $k++) {
                                $oct .= $this->s[$this->p++];
                            }
                            $out .= chr(octdec($oct) & 0xFF);
                        } else {
                            $out .= $n;
                        }
                }

                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                if (--$depth === 0) {
                    break;
                }
            }
            $out .= $c;
        }

        return new Str($out);
    }
}
