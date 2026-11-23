<?php

namespace App\Support;

use App\Models\User;

/**
 * The System Admin is not part of the office: the President Admin, staff and families never see the System Admin's account
 * or anything it does. Only the technical roles (System, Maintenance, Security) see it. (Admin Maintenance accounts stay
 * visible: the President can make them.) The System Admin in turn has no office permissions (orphans, money, chat...),
 * so it does not see the office either.
 */
final class SystemAccounts
{
    public const ROLES = ['system_admin'];

    /** activity only the System team does (updates, settings, Cloudflare, partner keys, sign-in limits, the President account) */
    public const ACTIONS = ['update.', 'settings.', 'cloudflare.', 'api_key.', 'limits.', 'president.', 'system.', 'install.'];

    /** Is the System team hidden from this person? (yes for everyone in the office) */
    public static function hiddenFrom(?User $viewer): bool
    {
        return ! $viewer || config("roles.roles.{$viewer->role}.kind") !== 'technical';
    }

    public static function isSystem(?User $u): bool
    {
        return $u !== null && in_array($u->role, self::ROLES, true);
    }

    /** @return list<int> the System team's account ids in a foundation */
    public static function ids(int $foundationId): array
    {
        return User::withoutGlobalScopes()->where('foundation_id', $foundationId)->whereIn('role', self::ROLES)->pluck('id')->map(fn ($i) => (int) $i)->all();
    }
}
