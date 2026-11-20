<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ApiKeyEndpointsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_system_admin_issues_lists_and_revokes_keys(): void
    {
        $this->world();
        $admin = $this->actingAsRole('system_admin');

        $r = $this->postJson('/api/system/api-keys', ['name' => 'Dar Al Ber'])->assertCreated();
        $raw = $r->json('data.key');
        $this->assertStringStartsWith('pk_', $raw);
        $id = $r->json('data.id');

        $stored = ApiKey::find($id);
        $this->assertSame(hash('sha256', $raw), $stored->key_hash);
        $this->assertSame($admin->foundation_id, $stored->foundation_id);

        $list = $this->getJson('/api/system/api-keys')->assertOk();
        $this->assertStringNotContainsString($raw, $list->getContent());
        $this->assertStringNotContainsString('key_hash', $list->getContent());

        // The key works on the partner route, then stops after revoking.
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Api-Key', $raw)->getJson('/api/partner/summary')->assertOk();
        $this->actingAsRole('system_admin');
        $this->deleteJson("/api/system/api-keys/$id")->assertOk()->assertJsonPath('data.revoked', true);
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Api-Key', $raw)->getJson('/api/partner/summary')->assertStatus(401);
    }

    public function test_bad_input_and_other_foundations_are_refused(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->postJson('/api/system/api-keys', [])->assertStatus(422);
        $this->postJson('/api/system/api-keys', ['name' => 'x', 'abilities' => ['everything']])->assertStatus(422);

        $other = ApiKey::create(['name' => 'o', 'key_prefix' => 'pk_x', 'key_hash' => 'h', 'abilities' => ['partner.summary'], 'foundation_id' => $this->otherFoundation()['foundation']->id ?? 999]);
        $this->deleteJson("/api/system/api-keys/{$other->id}")->assertNotFound();
    }
}
