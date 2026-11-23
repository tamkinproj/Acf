<?php

namespace App\Support\Pdf;

/** A PDF dictionary. Keys are kept without the slash, in their original order. */
class Dict
{
    /** @param array<string, mixed> $v */
    public function __construct(public array $v = [])
    {
    }

    public function get(string $k): mixed
    {
        return $this->v[$k] ?? null;
    }

    public function set(string $k, mixed $val): static
    {
        if ($val === null) {
            unset($this->v[$k]);
        } else {
            $this->v[$k] = $val;
        }

        return $this;
    }

    public function has(string $k): bool
    {
        return array_key_exists($k, $this->v);
    }

    /** The value of a name entry, e.g. "Widget" for /Subtype /Widget. */
    public function name(string $k): ?string
    {
        $x = $this->v[$k] ?? null;

        return $x instanceof Name ? $x->v : null;
    }

    /** A deep copy: nested dictionaries and arrays are copied too (references stay references). */
    public static function copy(mixed $x): mixed
    {
        if ($x instanceof Stream) {
            return new Stream(self::copy($x->dict), $x->data);
        }
        if ($x instanceof Dict) {
            return new Dict(array_map([self::class, 'copy'], $x->v));
        }
        if (is_array($x)) {
            return array_map([self::class, 'copy'], $x);
        }

        return $x;   // names, strings, refs are never changed in place
    }
}
