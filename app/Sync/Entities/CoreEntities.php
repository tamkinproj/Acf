<?php

namespace App\Sync\Entities;

use App\Core\Locations\LocationService;
use App\Core\Settings\SettingsCatalog;
use App\Core\Users\UserRules;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\Location;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Sync\EntityDefinition;
use App\Sync\RejectChange;
use App\Sync\SyncRegistry;
use App\Tenancy\TenantRule;

/** Registers the Phase 1 entities. Modules follow the same pattern from their own provider. */
final class CoreEntities
{
    public static function register(SyncRegistry $registry): void
    {
        $locations = app(LocationService::class);

        // Foundation profile: edited from devices, created only by the installer.
        $registry->register(new EntityDefinition(
            name: 'foundations',
            model: Foundation::class,
            ops: ['update' => 'foundation.update'],
            pullPermission: 'foundation.view',
            writable: ['name', 'legal_name', 'short_name', 'description', 'address', 'country', 'phone', 'email', 'website',
                'registration_number', 'registration_info', 'default_location_id'],
            rules: fn () => [
                'name' => ['sometimes', 'required', 'string', 'max:200'],
                'legal_name' => ['nullable', 'string', 'max:200'],
                'country' => ['nullable', 'string', 'max:100'],
                'short_name' => ['nullable', 'string', 'max:60'],
                'description' => ['nullable', 'string', 'max:5000'],
                'address' => ['nullable', 'string', 'max:1000'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email:rfc', 'max:190'],
                'website' => ['nullable', 'url:http,https', 'max:255'],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'registration_info' => ['nullable', 'string', 'max:5000'],
                'default_location_id' => ['nullable', 'uuid', TenantRule::exists(Location::class)],
            ],
        ));

        // Settings: every key is declared in SettingsCatalog; devices may only change values.
        $registry->register(new EntityDefinition(
            name: 'settings',
            model: Setting::class,
            ops: ['update' => 'settings.manage'],
            pullPermission: null,
            writable: ['value'],
            rules: function (string $op, ?Setting $existing) {
                return ['value' => $existing ? SettingsCatalog::rules($existing->key) : ['prohibited']];
            },
        ));

        // Users: devices may edit profile/role/status; creation (with a password) is online-only.
        $registry->register(new EntityDefinition(
            name: 'users',
            model: User::class,
            ops: ['update' => 'users.update', 'delete' => 'users.deactivate'],
            pullPermission: 'users.view',
            writable: ['name', 'email', 'phone', 'role_id', 'status', 'locale'],
            rules: fn (string $op, ?User $existing) => UserRules::rules($op, $existing),
            guard: fn (User $actor, string $op, ?User $target, array $fields) => UserRules::guard($actor, $op, $target, $fields),
        ));

        // Roles: read-only on devices (permissions are edited online via /api/roles).
        $registry->register(new EntityDefinition(name: 'roles', model: Role::class, pullPermission: null));

        // Devices: server-owned registry.
        $registry->register(new EntityDefinition(name: 'devices', model: Device::class, pullPermission: 'devices.view'));

        // Locations: the hierarchy, editable offline.
        $registry->register(new EntityDefinition(
            name: 'locations',
            model: Location::class,
            ops: ['create' => 'locations.manage', 'update' => 'locations.manage', 'delete' => 'locations.manage'],
            pullPermission: 'locations.view',
            writable: ['parent_id', 'level', 'name', 'code', 'latitude', 'longitude', 'is_active'],
            rules: fn (string $op, ?Location $existing, array $fields) => $locations->rules($op, $existing, $fields),
            guard: function ($actor, string $op, ?Location $existing) use ($locations) {
                if ($op === 'delete') {
                    $locations->guardDelete($existing);
                }
            },
            prepare: fn (string $op, ?Location $existing, array $fields) => $locations->prepare($op, $existing, $fields),
        ));

        // Audit log: devices may APPEND events (offline login, local actions); never edit or remove.
        // Identity fields (device, user, name, ip) are stamped by the server, not trusted from the device.
        $registry->register(new EntityDefinition(
            name: 'audit_logs',
            model: AuditLog::class,
            ops: ['create' => 'sync.use'],
            pullPermission: 'audit.view',
            writable: ['occurred_at', 'action', 'subject_type', 'subject_id', 'summary', 'old_values', 'new_values', 'correlation_id'],
            rules: fn () => [
                'occurred_at' => ['required', 'date'],
                'action' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_.]+$/'],
                'subject_type' => ['nullable', 'string', 'max:64'],
                'subject_id' => ['nullable', 'string', 'max:36'],
                'summary' => ['required', 'string', 'max:500'],
                'old_values' => ['nullable', 'array'],
                'new_values' => ['nullable', 'array'],
                'correlation_id' => ['nullable', 'uuid'],
            ],
            prepare: function (string $op, $existing, array $fields) {
                $ctx = app(\App\Sync\DeviceContext::class);
                $actor = auth()->user();

                return $fields + [
                    'device_id' => $ctx->deviceId(),
                    'user_id' => $actor?->getKey(),
                    'user_name' => $actor?->name,
                    'ip_address' => $ctx->ip(),
                ];
            },
        ));
    }
}
