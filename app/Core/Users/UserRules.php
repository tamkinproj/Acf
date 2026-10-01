<?php

namespace App\Core\Users;

use App\Core\Access\PermissionCatalog;
use App\Core\Access\RoleCatalog;
use App\Core\Settings\SettingsCatalog;
use App\Models\Role;
use App\Models\User;
use App\Sync\RejectChange;
use App\Tenancy\TenantRule;
use Illuminate\Validation\Rule;

/**
 * Validation and safety invariants for FOUNDATION user records. Shared by the user REST endpoints AND the sync engine,
 * so the same rules hold whichever path wrote. Everything resolves inside the current foundation: a role or user from
 * another foundation simply does not exist here.
 */
class UserRules
{
    public static function rules(string $op, ?User $existing): array
    {
        $req = $op === 'create' ? ['required'] : ['sometimes', 'required'];

        return [
            'name' => [...$req, 'string', 'max:150'],
            'email' => [...$req, 'email:rfc', 'max:190', self::uniqueEmail($existing)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => [...$req, 'uuid', TenantRule::exists(Role::class, 'id', fn ($q) => $q->where('scope', PermissionCatalog::FOUNDATION))],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'locale' => ['nullable', Rule::in(SettingsCatalog::LOCALES)],
        ];
    }

    /** Email is unique across the whole platform (it is the sign-in name), including removed accounts. */
    public static function uniqueEmail(?User $existing): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($existing) {
            $taken = User::withoutGlobalScopes()->withTrashed()->whereRaw('lower(email) = ?', [strtolower((string) $value)])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->getKey()))->exists();
            if ($taken) {
                $fail('This email address is already in use.');
            }
        };
    }

    /** @throws RejectChange */
    public static function guard(User $actor, string $op, ?User $target, array $fields): void
    {
        $newRole = isset($fields['role_id']) ? Role::query()->find($fields['role_id']) : null;
        $touchesAdmin = ($newRole?->key === RoleCatalog::FOUNDATION_ADMIN) || ($target?->isFoundationAdmin() ?? false);

        if ($touchesAdmin && ! $actor->isFoundationAdmin()) {
            throw new RejectChange('forbidden', 'Only a Foundation Admin can create, change or remove Foundation Admin accounts.');
        }
        if (isset($fields['status']) && $fields['status'] !== ($target?->status ?? 'active') && ! $actor->hasPermission('users.deactivate')) {
            throw new RejectChange('forbidden', 'You do not have permission to activate or deactivate users.');
        }
        if ($target && $target->getKey() === $actor->getKey()) {
            if (isset($fields['role_id']) && $fields['role_id'] !== $target->role_id) {
                throw new RejectChange('forbidden', 'You cannot change your own role.');
            }
            if (($fields['status'] ?? 'active') !== 'active' || $op === 'delete') {
                throw new RejectChange('forbidden', 'You cannot disable or delete your own account.');
            }
        }
        if ($target && $target->isFoundationAdmin()) {
            $losing = $op === 'delete'
                || (isset($fields['status']) && $fields['status'] !== 'active')
                || (isset($fields['role_id']) && $newRole?->key !== RoleCatalog::FOUNDATION_ADMIN);
            if ($losing && self::activeFoundationAdmins()->where('id', '!=', $target->getKey())->count() === 0) {
                throw new RejectChange('last_foundation_admin', 'The last active Foundation Admin cannot be disabled, demoted or removed.');
            }
        }
    }

    private static function activeFoundationAdmins()
    {
        return User::query()->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('key', RoleCatalog::FOUNDATION_ADMIN));
    }
}
