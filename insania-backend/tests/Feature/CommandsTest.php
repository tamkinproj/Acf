<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_an_issued_api_key_works_and_only_its_hash_is_stored(): void
    {
        $this->world();
        $this->withoutMockingConsoleOutput();

        $this->assertSame(0, Artisan::call('insania:create-api-key', ['name' => 'Dar Al Ber', 'foundation_id' => 1]));
        $raw = trim(collect(explode("\n", trim(Artisan::output())))->last());

        $this->assertStringStartsWith('pk_', $raw);
        $stored = ApiKey::first();
        $this->assertSame(hash('sha256', $raw), $stored->key_hash);
        $this->assertStringNotContainsString($raw, json_encode($stored->toArray()));
        $this->assertTrue($stored->isActive());

        $this->getJson('/api/partner/summary?month=2026-11', ['X-Api-Key' => $raw])->assertOk()->assertJsonPath('data.sponsored_children', 12);
    }

    public function test_an_api_key_for_a_missing_foundation_is_refused(): void
    {
        $this->world();

        $this->artisan('insania:create-api-key', ['name' => 'X', 'foundation_id' => 999])->assertFailed();
        $this->assertSame(0, ApiKey::count());
    }

    public function test_the_system_admin_command_creates_one_and_rejects_weak_passwords(): void
    {
        $this->world();

        $this->artisan('insania:create-system-admin', ['name' => 'Weak', 'email' => 'weak@insania.test', '--password' => 'short'])->assertFailed();
        $this->assertSame(0, User::where('email', 'weak@insania.test')->count());

        $this->artisan('insania:create-system-admin', ['name' => 'Strong', 'email' => 'strong@insania.test', '--password' => 'AStrongOne#2026'])->assertSuccessful();
        $this->assertSame('system_admin', User::where('email', 'strong@insania.test')->value('role'));
    }

    public function test_demo_data_is_never_seeded_in_production(): void
    {
        $this->app['env'] = 'production';
        (new \Database\Seeders\DatabaseSeeder)->run();

        $this->assertSame(0, User::count());
    }
}
