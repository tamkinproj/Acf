<?php

namespace App\Support\Pdf;

/** A reference to another object: "12 0 R". */
final class Ref
{
    public function __construct(public int $num, public int $gen = 0)
    {
    }
}
