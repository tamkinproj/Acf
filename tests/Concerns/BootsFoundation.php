<?php

namespace Tests\Concerns;

use App\Core\Access\RoleSeeder;
use App\Core\Devices\DeviceService;
use App\Core\Settings\SettingsService;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\Role;
use App\Models\User;
use App\Sync\DeviceContext;
use Illuminate\Support\Str;

/** Builds an installed system (roles, settings, foundation, primary device, super admin) without the wizard. */
trait BootsFoundation
{
    protected User $admin;
    protected Device $device;
    protected string $deviceToken;

    protected function bootFoundation(): void
    {
        $this->markInstalled();
        app(RoleSeeder::class)->seed();
        app(SettingsService::class)->ensureDefaults();
        Foundation::create(['name' => 'Test Foundation', 'short_name' => 'TF']);

        $this->admin = $this->makeUser('super_admin', 'admin@example.test');

        [$this->device] = app(DeviceService::class)->register('Main Office', 'office', $this->admin->id, primary: true, withToken: false);
        $this->deviceToken = app(DeviceService::class)->issueToken($this->device);
        app(DeviceContext::class)->forgetPrimary();
    }

    protected function makeUser(string $roleKey, ?string $email = null, string $password = 'Correct-horse-9'): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $roleKey)).' '.Str::random(4),
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
            'password' => $password,
            'role_id' => Role::where('key', $roleKey)->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    /** Authenticated as $user, carrying the registered device's token (as the PWA's sync client does). */
    protected function asDevice(User $user, ?string $token = null): static
    {
        return $this->actingAs($user)->withToken($token ?? $this->deviceToken);
    }

    protected function newDevice(string $name = 'Field Phone'): array
    {
        [$device, $token] = app(DeviceService::class)->register($name, 'field', $this->admin->id);

        return [$device, $token];
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
