<?php

namespace App\Support\Pdf;

/** A PDF name such as /Type (stored without the slash). */
final class Name
{
    public function __construct(public string $v)
    {
    }
}
