<?php

namespace App\Support;

use App\Models\Partner;

/** The partner the current request works in (set by the PartnerScope middleware; empty everywhere else). */
final class PartnerContext
{
    private static ?Partner $partner = null;

    public static function set(?Partner $p): void
    {
        self::$partner = $p;
    }

    public static function current(): ?Partner
    {
        return self::$partner;
    }

    public static function id(): ?int
    {
        return self::$partner?->id;
    }
}
