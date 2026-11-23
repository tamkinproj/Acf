<?php

namespace App\Support\Pdf;

/** A PDF string: the raw bytes, and whether it was written as <hex>. */
final class Str
{
    public function __construct(public string $v, public bool $hex = false)
    {
    }

    /** A text string as a PDF wants it: plain when ASCII, otherwise UTF-16BE with its mark. */
    public static function text(string $utf8): self
    {
        if (preg_match('/^[\x20-\x7e]*$/', $utf8)) {
            return new self($utf8);
        }

        return new self("\xFE\xFF".mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8'), true);
    }

    /** The text inside (UTF-16 or PDFDoc decoded to UTF-8). */
    public function utf8(): string
    {
        if (str_starts_with($this->v, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($this->v, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($this->v, "\xEF\xBB\xBF")) {
            return substr($this->v, 3);
        }

        return (string) mb_convert_encoding($this->v, 'UTF-8', 'ISO-8859-1');
    }
}
