<?php

namespace App\Services\OrphanReports;

/** A small repeatable random number source: the same seed always gives the same choices. */
final class Rand
{
    private int $s;

    public function __construct(int $seed)
    {
        $this->s = ($seed & 0x7FFFFFFF) ?: 0x2545F491;
    }

    /** A whole number from 0 to $n - 1. */
    public function pick(int $n): int
    {
        // xorshift, kept to 31 bits so it behaves the same on every server
        $x = $this->s;
        $x ^= ($x << 13) & 0x7FFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0x7FFFFFFF;
        $this->s = ($x & 0x7FFFFFFF) ?: 0x2545F491;

        return $n > 0 ? $this->s % $n : 0;
    }
}
