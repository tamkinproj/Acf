<?php

namespace Tests\Feature;

use App\Models\Child;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Offline changes are sent later, sometimes twice. Sending the same change twice must do it once. */
class IdempotencyTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_the_same_key_creates_one_child_and_replays_the_answer(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $before = Child::count();
        $h = ['Idempotency-Key' => 'offline-add-0001'];

        $first = $this->postJson('/api/children', ['name_en' => 'Offline Child'], $h)->assertCreated();
        $again = $this->postJson('/api/children', ['name_en' => 'Offline Child'], $h)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($before + 1, Child::count());
        $this->assertSame($first->json('data.id'), $again->json('data.id'));
    }

    public function test_a_different_key_is_a_new_change(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $before = Child::count();
        $this->postJson('/api/children', ['name_en' => 'One'], ['Idempotency-Key' => 'offline-add-0002'])->assertCreated();
        $this->postJson('/api/children', ['name_en' => 'Two'], ['Idempotency-Key' => 'offline-add-0003'])->assertCreated();
        $this->assertSame($before + 2, Child::count());
    }

    public function test_without_a_key_nothing_changes(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $before = Child::count();
        $this->postJson('/api/children', ['name_en' => 'One'])->assertCreated();
        $this->postJson('/api/children', ['name_en' => 'One'])->assertCreated();
        $this->assertSame($before + 2, Child::count());
    }

    public function test_a_key_cannot_be_reused_for_something_else_or_malformed(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $this->postJson('/api/children', ['name_en' => 'One'], ['Idempotency-Key' => 'offline-add-0004'])->assertCreated();
        $this->patchJson('/api/children/'.$this->childId('ACF-0001'), ['city' => 'Molave'], ['Idempotency-Key' => 'offline-add-0004'])
            ->assertStatus(422)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->postJson('/api/children', ['name_en' => 'One'], ['Idempotency-Key' => 'short'])->assertStatus(422)->assertJsonPath('code', 'BAD_IDEMPOTENCY_KEY');
    }

    public function test_keys_belong_to_one_person_only(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $a = $this->postJson('/api/children', ['name_en' => 'Mine'], ['Idempotency-Key' => 'shared-key-0005'])->assertCreated()->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $b = $this->postJson('/api/children', ['name_en' => 'Theirs'], ['Idempotency-Key' => 'shared-key-0005'])->assertCreated()->json('data.id');
        $this->assertNotSame($a, $b);
    }

    public function test_marking_cash_twice_with_one_key_records_one_payment(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $cash = $this->childId('ACF-0003');
        $h = ['Idempotency-Key' => 'offline-cash-0006'];
        $this->postJson("/api/payments/$cash/mark-cash", ['month' => '2026-11'], $h)->assertOk();
        $this->postJson("/api/payments/$cash/mark-cash", ['month' => '2026-11'], $h)->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, \App\Models\SponsorshipPayment::where('month', '2026-11')->where('status', 'paid')->whereHas('sponsorship', fn ($q) => $q->where('child_id', $cash))->count());
    }

    public function test_a_new_partner_key_is_never_stored_for_replay(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $raw = $this->postJson('/api/system/api-keys', ['name' => 'Partner'], ['Idempotency-Key' => 'key-secret-0007'])->assertCreated()->json('data.key');
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('idempotency_keys')->count());
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('idempotency_keys')->where('body', 'like', '%'.$raw.'%')->exists());
    }
}
