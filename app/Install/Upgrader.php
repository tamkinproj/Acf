<?php

namespace App\Install;

use App\Core\Access\PermissionCatalog;
use App\Core\Access\RoleCatalog;
use App\Core\Access\RoleSeeder;
use App\Core\Audit\Auditor;
use App\Core\Settings\SettingsService;
use App\Models\Foundation;
use App\Models\Role;
use App\Models\SystemState;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;

/**
 * Brings an installation up to the code's release: migrations, then the data changes a release needs. One place used by
 * the command line (`foundation:upgrade`) and by the token-protected web page, so a host with no terminal can upgrade too.
 * Safe to run repeatedly; never drops anything.
 */
class Upgrader
{
    /** Permission keys renamed or split by a release, so existing role grants carry over. */
    private const RENAMED = [
        'foundation.manage' => ['foundation.update'],
        'users.manage' => ['users.create', 'users.update', 'users.deactivate'],
    ];

    public function __construct(
        private InstallState $state,
        private TenantContext $tenant,
        private RoleSeeder $roles,
        private SettingsService $settings,
    ) {}

    public function installedVersion(): ?string
    {
        return $this->state->lock()['version'] ?? null;
    }

    public function needed(): bool
    {
        $installed = $this->installedVersion();

        return $installed !== null && version_compare((string) config('foundation.version'), $installed, '>');
    }

    public function hasPlatformAdmin(): bool
    {
        return $this->tenant->asSystem(fn () => User::query()->whereNull('foundation_id')->whereNull('deleted_at')->exists());
    }

    /** @return array{from:?string,to:string,roles_migrated:int,permissions_added:int,needs_platform_admin:bool} */
    public function run(): array
    {
        $from = (string) SystemState::get('version', $this->installedVersion());
        Artisan::call('migrate', ['--force' => true]);

        $migrated = $this->tenant->asSystem(fn () => $this->migrateLegacyRoles());
        $added = $this->roles->upgrade();
        $this->tenant->asSystem(function () {
            foreach (Foundation::query()->pluck('id') as $id) {
                $this->settings->ensureDefaults([], $id);
            }
        });

        SystemState::put('version', config('foundation.version'));
        SystemState::put('schema_version', (int) config('foundation.schema_version'));
        if ($lock = $this->state->lock()) {
            $this->state->writeLock($lock['install_id'], (string) config('foundation.version'));
        }

        return [
            'from' => $from ?: null, 'to' => (string) config('foundation.version'),
            'roles_migrated' => $migrated, 'permissions_added' => count($added),
            'needs_platform_admin' => ! $this->hasPlatformAdmin(),
        ];
    }

    /**
     * An installation from before multi-tenancy had a "Super Admin" role. Its people become Foundation Admins of the
     * foundation they were already running, and renamed permissions are carried over on every role.
     */
    private function migrateLegacyRoles(): int
    {
        $count = 0;
        foreach (Foundation::query()->pluck('id') as $foundationId) {
            $count += $this->tenant->runAs($foundationId, function () use ($foundationId) {
                $changed = 0;
                $roles = $this->roles->ensureForFoundation($foundationId);

                foreach (Role::query()->get() as $role) {
                    $translated = $this->translate($role->permissions ?? []);
                    if ($translated !== array_values($role->permissions ?? [])) {
                        $role->permissions = $translated;
                        $role->save();
                        $changed++;
                    }
                }

                $legacy = Role::query()->where('key', 'super_admin')->first();
                if ($legacy) {
                    $admin = $roles[RoleCatalog::FOUNDATION_ADMIN];
                    $admin->permissions = RoleCatalog::permissionsFor(RoleCatalog::FOUNDATION_ADMIN);
                    $admin->save();
                    User::query()->where('role_id', $legacy->getKey())->get()->each(function (User $u) use ($admin) {
                        $u->role_id = $admin->getKey();
                        $u->save();
                    });
                    $legacy->delete();
                    app(Auditor::class)->record('role.migrated', 'Super Admin accounts became Foundation Admins', 'roles', $admin->getKey());
                    $changed++;
                }

                return $changed;
            });
        }

        return $count;
    }

    /** @param list<string> $permissions @return list<string> */
    private function translate(array $permissions): array
    {
        $out = [];
        foreach ($permissions as $key) {
            foreach (self::RENAMED[$key] ?? [$key] as $new) {
                if (PermissionCatalog::exists($new)) {
                    $out[] = $new;
                }
            }
        }

        return array_values(array_unique($out));
    }
}
