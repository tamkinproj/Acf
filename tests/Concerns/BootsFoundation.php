<?php

namespace Tests\Concerns;

use App\Core\Access\RoleSeeder;
use App\Core\Devices\DeviceService;
use App\Core\Platform\FoundationProvisioner;
use App\Core\Settings\SettingsService;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\Role;
use App\Models\User;
use App\Sync\DeviceContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Builds an installed platform with one foundation ("Test Foundation") and its administrator, without the wizard.
 * Direct model calls in a test act inside that foundation unless a test switches context on purpose.
 */
trait BootsFoundation
{
    protected Foundation $foundation;
    protected User $admin;            // Foundation Admin of $foundation
    protected User $platformAdmin;
    protected Device $device;
    protected string $deviceToken;

    protected function bootFoundation(): void
    {
        $this->markInstalled();
        $tenant = app(TenantContext::class);
        $tenant->reset();

        $platformRole = app(RoleSeeder::class)->ensurePlatform();
        app(SettingsService::class)->ensureDefaults([], null, platform: true);
        $this->platformAdmin = $tenant->asSystem(fn () => User::create([
            'foundation_id' => null, 'name' => 'Platform Admin', 'email' => 'platform@example.test',
            'password' => 'Correct-horse-9', 'role_id' => $platformRole->id, 'status' => 'active',
        ]));
        app(RoleSeeder::class)->markSeen();

        [$this->foundation, $this->admin] = $this->createFoundation('Test Foundation', 'admin@example.test', 'TF');
        $this->inFoundation($this->foundation);

        $this->device = Device::query()->where('is_primary', true)->firstOrFail();
        $this->deviceToken = app(DeviceService::class)->issueToken($this->device);
        app(DeviceContext::class)->forgetPrimary();
    }

    /** @return array{0:Foundation,1:User} */
    protected function createFoundation(string $name, string $adminEmail, ?string $short = null): array
    {
        $result = app(FoundationProvisioner::class)->create(
            ['name' => $name, 'short_name' => $short],
            ['name' => 'Admin of '.$name, 'email' => $adminEmail, 'password' => 'Correct-horse-9'],
        );

        return [$result['foundation'], $result['admin']];
    }

    /** Make direct model calls in the test act inside this foundation. */
    protected function inFoundation(Foundation $foundation): static
    {
        app(TenantContext::class)->setTenant($foundation->getKey());
        app(DeviceContext::class)->forgetPrimary();
        app(SettingsService::class)->forget();

        return $this;
    }

    protected function inPlatform(): static
    {
        app(TenantContext::class)->setPlatform();

        return $this;
    }

    protected function makeUser(string $roleKey, ?string $email = null, string $password = 'Correct-horse-9', ?Foundation $foundation = null): User
    {
        $foundation ??= $this->foundation;

        return app(TenantContext::class)->runAs($foundation->getKey(), fn () => User::create([
            'name' => ucfirst(str_replace('_', ' ', $roleKey)).' '.Str::random(4),
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
            'password' => $password,
            'role_id' => Role::where('key', $roleKey)->firstOrFail()->id,
            'status' => 'active',
        ]));
    }

    /** A user whose role holds exactly these permissions (for testing permission gates without a built-in role). */
    protected function makeUserWithPermissions(array $permissions, ?string $email = null, ?Foundation $foundation = null): User
    {
        $foundation ??= $this->foundation;

        return app(TenantContext::class)->runAs($foundation->getKey(), function () use ($permissions, $email) {
            $role = Role::create(['key' => 'custom_'.Str::lower(Str::random(6)), 'name' => 'Custom', 'is_system' => false, 'scope' => 'foundation', 'permissions' => $permissions]);

            return User::create([
                'name' => 'Custom '.Str::random(4), 'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
                'password' => 'Correct-horse-9', 'role_id' => $role->id, 'status' => 'active',
            ]);
        });
    }

    /** Authenticated as $user, carrying the registered device's token (as the PWA's sync client does). */
    protected function asDevice(User $user, ?string $token = null): static
    {
        return $this->actingAs($user)->withToken($token ?? $this->deviceToken);
    }

    protected function newDevice(string $name = 'Field Phone'): array
    {
        return app(DeviceService::class)->register($name, 'field', $this->admin->id);
    }

    /** Build a push change. */
    protected function change(string $entity, string $op, array $fields = [], ?string $id = null, ?int $base = null): array
    {
        return array_filter([
            'change_id' => (string) Str::uuid7(),
            'entity' => $entity,
            'entity_id' => $id ?? (string) Str::uuid7(),
            'op' => $op,
            'base_version' => $base,
            'fields' => $fields ?: null,
            'client_ts' => now()->toIso8601String(),
        ], fn ($v) => $v !== null);
    }
}
