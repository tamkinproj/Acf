<?php

namespace Tests\Feature;

use App\Install\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The upgrade, starting from a database exactly as release 1.0.0 left it: only the 1.0.0 migrations, a "Super Admin",
 * a staff member and a place - and no tenancy columns at all. (WebUpgradeTest works on the new schema; this one proves
 * the upgrade page itself never touches a column the upgrade has not created yet.)
 */
class UpgradeFromLegacySchemaTest extends TestCase
{
    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        // Back to an empty database (dropAllTables() cannot run inside the SQLite test transaction).
        Schema::withoutForeignKeyConstraints(function () {
            foreach (array_unique(Schema::getTableListing(schemaQualified: false)) as $table) {
                Schema::dropIfExists($table);   // MySQL may also list same-named tables of other databases
            }
        });
        $old = array_filter(glob(database_path('migrations/*.php')), fn ($f) => basename($f) < '2026_10_02');
        Artisan::call('migrate', ['--path' => array_values($old), '--realpath' => true, '--force' => true]);
        $this->assertFalse(Schema::hasColumn('users', 'foundation_id'), 'the starting point is the 1.0.0 schema');
        $this->assertFalse(Schema::hasTable('programs'));

        $now = now();
        $row = fn (array $r) => $r + ['id' => (string) Str::uuid7(), 'version' => 1, 'created_at' => $now, 'updated_at' => $now];
        DB::table('foundations')->insert($row(['name' => 'Al-Noor Foundation', 'short_name' => 'Al-Noor']));
        $roles = [];
        foreach ([
            'super_admin' => ['*'], 'foundation_admin' => ['foundation.view', 'foundation.manage'], 'staff' => ['dashboard.view', 'users.manage', 'locations.view'],
            'field_worker' => ['dashboard.view'], 'volunteer' => ['dashboard.view'], 'viewer' => ['dashboard.view'],
        ] as $key => $perms) {
            DB::table('roles')->insert($r = $row(['key' => $key, 'name' => Str::headline($key), 'is_system' => true, 'permissions' => json_encode($perms)]));
            $roles[$key] = $r['id'];
        }
        DB::table('users')->insert($admin = $row(['name' => 'Old Admin', 'email' => 'old@acf.test', 'password' => bcrypt('Old-admin-pass-1'), 'role_id' => $roles['super_admin'], 'status' => 'active']));
        $this->adminId = $admin['id'];
        DB::table('users')->insert($row(['name' => 'Staff', 'email' => 'staff@acf.test', 'password' => bcrypt('Staff-pass-123'), 'role_id' => $roles['staff'], 'status' => 'active']));
        DB::table('locations')->insert($row(['level' => 'country', 'name' => 'Philippines', 'path' => '/ph/', 'depth' => 0]));
        DB::table('settings')->insert($row(['key' => 'app.name', 'group' => 'general', 'value' => json_encode('Al-Noor')]));
        DB::table('devices')->insert($row(['device_code' => 'FOUNDATION-DEVICE-OLD00001', 'name' => 'Main Office', 'type' => 'office', 'is_primary' => true]));
        DB::table('system_state')->insert(['key' => 'version', 'value' => json_encode('1.0.0')]);

        config(['foundation.install_id' => 'legacy-install', 'foundation.installer_require_token' => true]);
        app(InstallState::class)->writeLock('legacy-install', '1.0.0');
    }

    protected function tearDown(): void
    {
        // On MySQL/MariaDB the DDL above ends the test transaction, so the next test must rebuild the database itself.
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_a_1_0_0_database_upgrades_from_the_web_page_and_keeps_everything(): void
    {
        $this->get('/')->assertRedirect('/upgrade');
        $this->get('/upgrade')->assertRedirect('/upgrade/token');
        $token = app(InstallState::class)->token();
        $this->post('/upgrade/token', ['token' => $token])->assertRedirect('/upgrade');

        // Nothing may ask the old database about a column only the upgrade creates. MySQL/MariaDB fail such a query
        // ("Unknown column users.foundation_id" - a real 500 on a live site); SQLite silently reads it as a string, so
        // record every query issued before the migrations start and check them by name.
        $beforeMigrating = [];
        $migrating = false;
        DB::listen(function ($q) use (&$beforeMigrating, &$migrating) {
            $migrating = $migrating || preg_match('/^\s*(alter|create) table/i', $q->sql);
            if (! $migrating) {
                $beforeMigrating[] = $q->sql;
            }
        });

        $this->get('/upgrade')->assertOk()->assertSee('Platform Admin')->assertSee('name="admin_password"', false);

        $this->post('/upgrade/run', [
            'name' => 'Platform Owner', 'email' => 'owner@platform.test',
            'admin_password' => 'Platform-pass-123', 'admin_password_confirmation' => 'Platform-pass-123',
        ])->assertOk()->assertSee('Update complete');

        $this->assertTrue($migrating, 'the migrations ran');
        $this->assertNotEmpty($beforeMigrating);
        foreach (['foundation_id', 'scope', 'module', 'programs', 'status_reason'] as $newName) {
            $this->assertEmpty(preg_grep('/\b'.$newName.'\b/', $beforeMigrating), "a query touched `{$newName}` before the upgrade created it");
        }

        $this->assertTrue(Schema::hasColumn('users', 'foundation_id'));
        $foundation = DB::table('foundations')->first();
        $this->assertSame(['Al-Noor Foundation', 'active'], [$foundation->name, $foundation->status]);
        $role = fn (string $email) => DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('email', $email)->value('roles.key');
        $this->assertSame('foundation_admin', $role('old@acf.test'), 'the old Super Admin runs their foundation');
        $this->assertSame('staff', $role('staff@acf.test'));
        $this->assertSame('platform_admin', $role('owner@platform.test'));
        $this->assertSame(0, DB::table('users')->where('email', '!=', 'owner@platform.test')->whereNull('foundation_id')->count(), 'every old account belongs to the foundation');
        $this->assertSame(0, DB::table('locations')->whereNull('foundation_id')->count());
        $this->assertSame(0, DB::table('settings')->where('key', 'app.name')->whereNull('foundation_id')->count());
        $this->assertSame('2.0.0', app(InstallState::class)->lock()['version']);

        // The site opens normally afterwards, and both accounts can sign in.
        $this->get('/upgrade')->assertRedirect('/');
        $this->postJson('/api/auth/login', ['email' => 'old@acf.test', 'password' => 'Old-admin-pass-1'])->assertOk()->assertJsonPath('data.kind', 'foundation');
        $this->postJson('/api/auth/logout');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'owner@platform.test', 'password' => 'Platform-pass-123'])->assertOk()->assertJsonPath('data.kind', 'platform');
    }
}
