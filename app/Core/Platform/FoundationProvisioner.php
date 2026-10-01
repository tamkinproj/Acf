<?php

namespace App\Core\Platform;

use App\Core\Access\RoleCatalog;
use App\Core\Access\RoleSeeder;
use App\Core\Audit\Auditor;
use App\Core\Devices\DeviceService;
use App\Core\Settings\SettingsService;
use App\Core\Users\PasswordPolicy;
use App\Models\Foundation;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a complete, working foundation in one transaction: the tenant itself, its own copy of the roles, its settings,
 * its server device and its first Foundation Admin. Nothing is shared with any other foundation.
 */
class FoundationProvisioner
{
    public function __construct(
        private TenantContext $tenant,
        private RoleSeeder $roles,
        private SettingsService $settings,
        private DeviceService $devices,
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string,mixed>  $data  foundation attributes
     * @param  array{name:string,email:string,phone?:?string,password?:?string}  $admin
     * @return array{foundation:Foundation,admin:User,temporary_password:?string}
     */
    public function create(array $data, array $admin, ?User $actor = null): array
    {
        return $this->tenant->asSystem(fn () => DB::transaction(function () use ($data, $admin, $actor) {
            $foundation = new Foundation;
            $foundation->forceFill($this->attributes($data) + ['slug' => $this->uniqueSlug($data['slug'] ?? $data['short_name'] ?? $data['name'])])->save();
            $id = $foundation->getKey();

            $created = $this->auditor->muted(function () use ($id, $foundation, $data, $admin) {
                $roles = $this->roles->ensureForFoundation($id);
                $this->settings->ensureDefaults([
                    'app.name' => $foundation->short_name ?: $foundation->name,
                    'app.timezone' => $data['timezone'] ?? config('app.display_timezone', 'Asia/Manila'),
                    'app.locale' => $data['locale'] ?? 'en',
                    'app.currency' => $data['currency'] ?? 'PHP',
                    'deployment.model' => $data['deployment_model'] ?? 'central',
                ], $id);
                $this->devices->register('Server', 'server', null, primary: true, withToken: false, foundationId: $id);

                $password = $admin['password'] ?? null;
                $temporary = $password === null ? PasswordPolicy::generateTemporary() : null;
                $user = new User;
                $user->forceFill([
                    'foundation_id' => $id,
                    'name' => $admin['name'],
                    'email' => strtolower($admin['email']),
                    'phone' => $admin['phone'] ?? null,
                    'password' => $password ?? $temporary,
                    'role_id' => $roles[RoleCatalog::FOUNDATION_ADMIN]->getKey(),
                    'status' => 'active',
                    'must_change_password' => $temporary !== null,
                ])->save();

                return [$user, $temporary];
            });

            [$user, $temporary] = $created;
            $this->auditor->record('foundation.provisioned', "Foundation \"{$foundation->name}\" was set up with its first administrator", 'foundations', $id,
                null, ['administrator' => $user->email], $actor, foundationId: $id);

            return ['foundation' => $foundation->refresh(), 'admin' => $user, 'temporary_password' => $temporary];
        }));
    }

    /** @return array<string,mixed> */
    private function attributes(array $data): array
    {
        return array_merge(
            ['status' => Foundation::ACTIVE],
            array_intersect_key($data, array_flip([
                'name', 'legal_name', 'short_name', 'description', 'address', 'country', 'phone', 'email', 'website',
                'registration_number', 'registration_info',
            ])),
        );
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'foundation';
        $slug = $base;
        for ($i = 2; Foundation::withoutGlobalScopes()->withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
