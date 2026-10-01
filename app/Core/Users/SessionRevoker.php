<?php

namespace App\Core\Users;

use Illuminate\Support\Facades\DB;

/** Server-side session control (requires the database session driver, which the installer configures). */
class SessionRevoker
{
    public function enabled(): bool
    {
        return config('session.driver') === 'database';
    }

    /** Kill every session of a user, optionally keeping the current one. */
    public function revokeAll(string $userId, ?string $exceptSessionId = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        return DB::table('sessions')->where('user_id', $userId)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))->delete();
    }

    /** @return list<array{handle:string,ip:?string,agent:?string,last_active:string,current:bool}> */
    public function list(string $userId, string $currentSessionId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return DB::table('sessions')->where('user_id', $userId)->orderByDesc('last_activity')->get()
            ->map(fn ($s) => [
                'handle' => substr(hash('sha256', $s->id), 0, 24),   // never expose the real session id
                'ip' => $s->ip_address,
                'agent' => $s->user_agent ? substr($s->user_agent, 0, 160) : null,
                'last_active' => date('c', $s->last_activity),
                'current' => $s->id === $currentSessionId,
            ])->all();
    }

    public function revokeByHandle(string $userId, string $handle, string $currentSessionId): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        foreach (DB::table('sessions')->where('user_id', $userId)->pluck('id') as $id) {
            if ($id !== $currentSessionId && hash_equals(substr(hash('sha256', $id), 0, 24), $handle)) {
                return DB::table('sessions')->where('id', $id)->delete() > 0;
            }
        }

        return false;
    }
}
