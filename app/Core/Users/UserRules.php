<?php

namespace App\Core\Users;

use App\Core\Access\RoleCatalog;
use App\Core\Settings\SettingsCatalog;
use App\Models\Role;
use App\Models\User;
use App\Sync\RejectChange;
use Illuminate\Validation\Rule;

/**
 * Validation and safety invariants for user records. Shared by the user REST
 * endpoints AND the sync engine, so the same rules hold whichever path wrote.
 */
class UserRules
{
    public static function rules(string $op, ?User $existing): array
    {
        $req = $op === 'create' ? ['required'] : ['sometimes', 'required'];

        return [
            'name' => [...$req, 'string', 'max:150'],
            'email' => [...$req, 'email:rfc', 'max:190', function (string $attribute, mixed $value, \Closure $fail) use ($existing) {
                $taken = User::withTrashed()->whereRaw('lower(email) = ?', [strtolower((string) $value)])
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->getKey()))->exists();
                if ($taken) {
                    $fail('This email address is already in use (including deactivated or removed accounts).');
                }
            }],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => [...$req, 'uuid', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'locale' => ['nullable', Rule::in(SettingsCatalog::LOCALES)],
        ];
    }

    /** @throws RejectChange */
    public static function guard(User $actor, string $op, ?User $target, array $fields): void
    {
        $newRole = isset($fields['role_id']) ? Role::query()->find($fields['role_id']) : null;
        $touchesSuper = ($newRole?->key === RoleCatalog::SUPER_ADMIN) || ($target?->isSuperAdmin() ?? false);

        if ($touchesSuper && ! $actor->isSuperAdmin()) {
            throw new RejectChange('forbidden', 'Only a Super Admin can create, change or remove Super Admin accounts.');
        }
        if ($target && $target->getKey() === $actor->getKey()) {
            if (isset($fields['role_id']) && $fields['role_id'] !== $target->role_id) {
                throw new RejectChange('forbidden', 'You cannot change your own role.');
            }
            if (($fields['status'] ?? 'active') !== 'active' || $op === 'delete') {
                throw new RejectChange('forbidden', 'You cannot disable or delete your own account.');
            }
        }
        if ($target && $target->isSuperAdmin()) {
            $losing = $op === 'delete'
                || (isset($fields['status']) && $fields['status'] !== 'active')
                || (isset($fields['role_id']) && $newRole?->key !== RoleCatalog::SUPER_ADMIN);
            if ($losing && self::activeSuperAdmins()->where('id', '!=', $target->getKey())->count() === 0) {
                throw new RejectChange('last_super_admin', 'The last active Super Admin cannot be disabled, demoted or removed.');
            }
        }
    }

    private static function activeSuperAdmins()
    {
        return User::query()->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('key', RoleCatalog::SUPER_ADMIN));
    }
}
