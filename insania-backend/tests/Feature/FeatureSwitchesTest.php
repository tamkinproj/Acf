<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class FeatureSwitchesTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function shipped(): void
    {
        // exactly what config/features.php ships with
        $file = require base_path('config/features.php');
        config(['features.switches' => $file['switches']]);
    }

    private function as(string $role): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole($role);
    }

    public function test_every_office_feature_ships_switched_off(): void
    {
        $file = require base_path('config/features.php');
        $this->assertNotEmpty($file['switches']);
        foreach ($file['switches'] as $feature => $on) {
            $this->assertFalse($on, "$feature should ship switched off");
        }
    }

    public function test_switched_off_features_are_refused_by_the_server_for_every_role(): void
    {
        $this->world();
        $this->shipped();
        $office = [
            ['GET', '/api/children'], ['POST', '/api/children'], ['GET', '/api/payments'], ['GET', '/api/projects'], ['GET', '/api/reports/summary'],
            ['GET', '/api/site'], ['POST', '/api/site/publish'], ['GET', '/api/users'], ['POST', '/api/users'], ['GET', '/api/audit'], ['GET', '/api/my/family'],
        ];
        foreach (['president_admin', 'security_admin', 'staff:aytam', 'staff:finance', 'staff:projects', 'user', 'system_admin', 'admin_maintenance'] as $role) {
            $this->as($role);
            foreach ($office as [$m, $url]) {
                $this->json($m, $url)->assertForbidden();
            }
            $this->getJson('/api/me')->assertOk()->assertJsonMissing(['children.view'])->assertJsonMissing(['site.edit'])->assertJsonMissing(['users.manage']);
        }
    }

    public function test_the_system_admin_keeps_every_tool(): void
    {
        $this->world();
        $this->shipped();
        $this->as('system_admin');
        foreach (['/api/system/health', '/api/system/settings', '/api/system/api-keys', '/api/system/updates', '/api/system/president-admin'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/setup')->assertOk()->assertJsonPath('data.total', 3);
        $this->as('admin_maintenance');
        $this->getJson('/api/system/health')->assertOk();
    }

    public function test_the_president_signs_in_sees_an_empty_office_and_can_change_their_password(): void
    {
        $this->world();
        $this->shipped();
        $p = User::where('role', 'president_admin')->first();
        $p->update(['password' => 'Old#Passw0rd123']);
        $token = $this->postJson('/api/login', ['email' => $p->email, 'password' => 'Old#Passw0rd123'])->assertOk()->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.permissions', []);
        $d = $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.features_off', true)->json('data');
        foreach (['children', 'payments', 'projects', 'security', 'family'] as $part) {
            $this->assertArrayNotHasKey($part, $d);
        }
        $this->getJson('/api/setup')->assertOk()->assertJsonPath('data.total', 0)->assertJsonPath('data.required_left', 0);
        $this->postJson('/api/me/password', ['current_password' => 'Old#Passw0rd123', 'password' => 'New#Passw0rd456'])->assertOk();
    }

    public function test_switching_one_feature_on_brings_back_only_that_feature(): void
    {
        $this->world();
        $this->shipped();
        config(['features.switches.projects' => true]);
        $this->as('president_admin');
        $this->getJson('/api/projects')->assertOk();
        $this->getJson('/api/children')->assertForbidden();
        $this->getJson('/api/site')->assertForbidden();
        $steps = collect($this->getJson('/api/setup')->assertOk()->json('data.steps'))->pluck('key')->all();
        $this->assertSame(['first_project'], $steps);
        $this->as('staff:projects');
        $this->getJson('/api/projects')->assertOk();
        $this->as('staff:finance');
        $this->getJson('/api/payments')->assertForbidden();
    }

    public function test_published_website_content_stays_visible_to_visitors(): void
    {
        $this->world();
        $this->shipped();
        $this->getJson('/api/public/site')->assertOk();
    }
}
