<?php

namespace App\Sync;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Declares how one entity takes part in synchronization. A future module
 * (Aytam, Relief...) adds itself by registering definitions - the sync engine
 * itself never changes.
 *
 * @property-read array<string,string|null> $ops  op => permission required ('create'|'update'|'delete').
 *            An op that is absent is NOT accepted from devices (server-owned data).
 */
final class EntityDefinition
{
    public const POLICY_MANUAL = 'manual';          // record a conflict, keep server value, preserve local change
    public const POLICY_SERVER_WINS = 'server_wins'; // record resolved conflict, discard local change
    public const POLICY_CLIENT_WINS = 'client_wins'; // apply, but record the overwrite

    /**
     * @param  class-string<Model>  $model
     * @param  array<string,string|null>  $ops
     * @param  list<string>  $writable  business fields a device may send
     * @param  Closure(string $op, ?Model $existing, array $fields): array  $rules  Laravel validation rules
     * @param  Closure(User $actor, string $op, ?Model $existing, array $fields): void|null  $guard  throw RejectChange to refuse
     * @param  Closure(string $op, ?Model $existing, array $fields): array|null  $prepare  derive server-controlled fields
     * @param  Closure(User $user, \App\Models\SyncChange $change): bool|null  $visible  row-level read scope for pull
     */
    public function __construct(
        public readonly string $name,
        public readonly string $model,
        public readonly array $ops = [],
        public readonly ?string $pullPermission = null,
        public readonly array $writable = [],
        public readonly ?Closure $rules = null,
        public readonly string $conflictPolicy = self::POLICY_MANUAL,
        public readonly ?Closure $guard = null,
        public readonly ?Closure $prepare = null,
        public readonly ?Closure $visible = null,
    ) {}

    public function rulesFor(string $op, ?Model $existing, array $fields): array
    {
        return $this->rules ? ($this->rules)($op, $existing, $fields) : [];
    }

    public function canPull(User $user): bool
    {
        return $this->pullPermission === null || $user->hasPermission($this->pullPermission);
    }

    public function canWrite(User $user, string $op): bool
    {
        return array_key_exists($op, $this->ops) && ($this->ops[$op] === null || $user->hasPermission($this->ops[$op]));
    }
}
