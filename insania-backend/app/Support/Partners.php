<?php

namespace App\Support;

use App\Models\Partner;

/** The partner list of a foundation: the four partners every installation starts with, and how a request picks one. */
final class Partners
{
    public const DEFAULT = 'dar_al_ber';

    public const STATUSES = ['active', 'paused', 'not_setup'];

    /** The partners every foundation has. Only Dar Al Ber is set up; the others wait for their own form. */
    public const LIST = [
        ['code' => 'dar_al_ber', 'name_en' => 'Dar Al Ber Society', 'name_ar' => 'جمعية دار البر', 'logo' => 'assets/partners/dar_al_ber.png', 'form_key' => 'dar_al_ber', 'status' => 'active', 'sort' => 1],
        ['code' => 'dubai_charity', 'name_en' => 'Dubai Charity Association', 'name_ar' => 'جمعية دبي الخيرية', 'logo' => 'assets/partners/dubai_charity.svg', 'form_key' => null, 'status' => 'not_setup', 'sort' => 2],
        ['code' => 'sharjah_charity', 'name_en' => 'Sharjah Charity International', 'name_ar' => 'جمعية الشارقة الخيرية', 'logo' => 'assets/partners/sharjah_charity.jpg', 'form_key' => null, 'status' => 'not_setup', 'sort' => 3],
        ['code' => 'ico', 'name_en' => 'International Charity Organization (ICO)', 'name_ar' => 'هيئة الأعمال الخيرية العالمية', 'logo' => 'assets/partners/ico.jpg', 'form_key' => null, 'status' => 'not_setup', 'sort' => 4],
    ];

    /** Makes sure the foundation has its partner list (new installs, and the first request after this update). */
    public static function ensure(int $foundationId): void
    {
        if ($foundationId <= 0 || Partner::withoutGlobalScopes()->where('foundation_id', $foundationId)->count() >= count(self::LIST)) {
            return;
        }
        foreach (self::LIST as $p) {
            Partner::withoutGlobalScopes()->firstOrCreate(['foundation_id' => $foundationId, 'code' => $p['code']], $p);
        }
    }

    public static function all(int $foundationId)
    {
        self::ensure($foundationId);

        return Partner::withoutGlobalScopes()->where('foundation_id', $foundationId)->orderBy('sort')->orderBy('id')->get();
    }

    public static function byCode(int $foundationId, string $code): ?Partner
    {
        self::ensure($foundationId);

        return Partner::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('code', $code)->first();
    }

    public static function defaultId(int $foundationId): ?int
    {
        return self::byCode($foundationId, self::DEFAULT)?->id;
    }

    /** The partner a request works in: the one asked for, else the first active one, else Dar Al Ber (even when paused). */
    public static function resolve(int $foundationId, ?string $code): ?Partner
    {
        $code = trim((string) $code);
        if ($code !== '') {
            return preg_match('/^[a-z0-9_]{2,40}$/', $code) ? self::byCode($foundationId, $code) : null;
        }

        return self::all($foundationId)->firstWhere('status', 'active') ?? self::byCode($foundationId, self::DEFAULT);
    }
}
