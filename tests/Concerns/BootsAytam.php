<?php

namespace Tests\Concerns;

use App\Models\Program;
use App\Models\Role;
use App\Models\User;

/** An active Aytam program with a Mushrif, a Field Worker and an outsider (foundation staff with no program role). */
trait BootsAytam
{
    use BootsFoundation;

    protected Program $program;
    protected User $mushrif;
    protected User $worker;
    protected User $outsider;

    protected function bootAytam(): void
    {
        $this->bootFoundation();
        $id = $this->actingAs($this->admin)->postJson('/api/programs', ['name' => 'Aytam Care', 'category' => 'aytam'])->assertCreated()->json('data.id');
        $this->postJson("/api/programs/{$id}/status", ['status' => 'active'])->assertOk();
        $this->program = Program::find($id);

        $this->mushrif = $this->makeUser('volunteer', 'mushrif@example.test');
        $this->worker = $this->makeUser('volunteer', 'worker@example.test');
        $this->outsider = $this->makeUser('staff', 'outsider@example.test');
        $this->join($this->mushrif, 'aytam_mushrif');
        $this->join($this->worker, 'aytam_field_worker');
    }

    protected function join(User $user, string $roleKey, ?Program $program = null): void
    {
        $roleId = Role::where('key', $roleKey)->value('id');
        $this->actingAs($this->admin)->putJson('/api/programs/'.($program ?? $this->program)->id."/team/{$user->id}", ['role_id' => $roleId])->assertSuccessful();
    }

    protected function base(?Program $program = null): string
    {
        return '/api/programs/'.($program ?? $this->program)->id;
    }

    protected function aytamPayload(array $over = []): array
    {
        return $over + ['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17', 'gender' => 'male', 'city' => 'Cotabato City'];
    }

    protected function createAytam(array $over = [], ?User $as = null): string
    {
        return $this->actingAs($as ?? $this->mushrif)->postJson($this->base().'/aytam', $this->aytamPayload($over))->assertCreated()->json('data.id');
    }
}
