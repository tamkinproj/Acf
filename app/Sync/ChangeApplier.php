<?php

namespace App\Sync;

use App\Models\Device;
use App\Models\SyncChange;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Applies ONE change pushed by a device.
 *
 * Guarantees:
 *  - Idempotent: a change_id is applied at most once (feed has a unique index);
 *    re-sending after a lost response returns the original outcome.
 *  - Never silently overwrites: if the record moved on since the device last
 *    saw it AND the same fields were touched (or it was deleted), the change is
 *    recorded as a conflict and the server value is kept.
 *  - Non-overlapping concurrent edits merge automatically.
 *  - Authorization and validation are re-checked on the server for every change.
 *
 * Result statuses: applied | duplicate | conflict | rejected
 */
class ChangeApplier
{
    private const META = ['id', 'version', 'created_at', 'updated_at', 'deleted_at', 'origin_device_id', 'created_by', 'updated_by', 'deleted_by'];

    public function __construct(private SyncRegistry $registry, private ChangeContext $changeContext) {}

    /** @return array<string,mixed> */
    public function apply(array $change, User $actor, Device $device): array
    {
        $env = Validator::make($change, [
            'change_id' => ['required', 'uuid'],
            'entity' => ['required', 'string', 'max:64'],
            'entity_id' => ['required', 'uuid'],
            'op' => ['required', 'in:create,update,delete'],
            'base_version' => ['nullable', 'integer', 'min:1'],
            'fields' => ['nullable', 'array'],
            'client_ts' => ['nullable', 'date'],
        ]);
        $base = ['change_id' => $change['change_id'] ?? null, 'entity' => $change['entity'] ?? null, 'entity_id' => $change['entity_id'] ?? null];
        if ($env->fails()) {
            return $base + ['status' => 'rejected', 'code' => 'invalid_envelope', 'message' => 'Malformed change.', 'errors' => $env->errors()->toArray()];
        }

        $def = $this->registry->get($change['entity']);
        if (! $def) {
            return $base + $this->reject('unknown_entity', "Unknown entity '{$change['entity']}'.");
        }
        if (! array_key_exists($change['op'], $def->ops)) {
            return $base + $this->reject('op_not_allowed', "'{$change['op']}' is not accepted for {$change['entity']} from devices.");
        }
        if (! $def->canWrite($actor, $change['op'])) {
            return $base + $this->reject('forbidden', 'You do not have permission to make this change.');
        }

        if ($prior = $this->priorOutcome($change['change_id'])) {
            return $base + $prior;
        }

        $this->changeContext->begin($change['change_id'], $change['client_ts'] ?? null);
        try {
            return $base + DB::transaction(fn () => $this->applyInTransaction($def, $change, $actor, $device));
        } catch (RejectChange $e) {
            return $base + $this->reject($e->reason, $e->getMessage(), $e->errors);
        } catch (ValidationException $e) {
            return $base + $this->reject('validation', 'Validation failed.', $e->errors());
        } catch (UniqueConstraintViolationException $e) {
            // Two concurrent pushes of the same change: the other one won, report its outcome.
            return $base + ($this->priorOutcome($change['change_id']) ?? $this->reject('duplicate_key', 'A record with these unique values already exists.'));
        } finally {
            $this->changeContext->end();
        }
    }

    private function applyInTransaction(EntityDefinition $def, array $c, User $actor, Device $device): array
    {
        /** @var class-string<Model> $class */
        $class = $def->model;
        $fields = $this->cleanFields($def, $c['fields'] ?? []);

        return match ($c['op']) {
            'create' => $this->create($def, $class, $c, $fields, $actor),
            'update' => $this->update($def, $class, $c, $fields, $actor, $device),
            'delete' => $this->delete($def, $class, $c, $actor, $device),
        };
    }

    private function create(EntityDefinition $def, string $class, array $c, array $fields, User $actor): array
    {
        if ($this->query($class)->whereKey($c['entity_id'])->exists()) {
            throw new RejectChange('already_exists', 'A record with this id already exists.');
        }
        $fields = $this->validated($def, 'create', null, $fields);
        $fields = $def->prepare ? ($def->prepare)('create', null, $fields + ['id' => $c['entity_id']]) : $fields;
        ($def->guard)?->__invoke($actor, 'create', null, $fields);

        $model = new $class;
        $model->forceFill($fields);
        $model->id = $c['entity_id'];
        $model->save();

        return ['status' => 'applied', 'version' => $model->version];
    }

    private function update(EntityDefinition $def, string $class, array $c, array $fields, User $actor, Device $device): array
    {
        $model = $this->query($class)->find($c['entity_id']) ?? throw new RejectChange('not_found', 'The record does not exist on the server.');
        $baseVersion = $c['base_version'] ?? throw new RejectChange('base_version_required', 'base_version is required for updates.');

        if ($this->trashed($model)) {
            return $this->conflict($def, $c, $model, $baseVersion, 'deleted_on_server', array_keys($fields), $fields, $actor);
        }

        $merged = false;
        if ($model->version !== $baseVersion) {
            [$changedSince, $deletedSince] = $this->changesSince($c['entity'], $c['entity_id'], $baseVersion, $device);
            $overlap = array_values(array_intersect(array_keys($fields), $changedSince));
            if ($deletedSince || $overlap !== []) {
                return $this->handlePolicyConflict($def, $c, $model, $baseVersion, $deletedSince ? 'deleted_on_server' : 'field_overlap', $overlap, $fields, $actor);
            }
            $merged = true;   // disjoint fields: safe automatic merge
        }

        return $this->write($def, $model, $c, $fields, $actor, $merged);
    }

    private function delete(EntityDefinition $def, string $class, array $c, User $actor, Device $device): array
    {
        $model = $this->query($class)->find($c['entity_id']) ?? throw new RejectChange('not_found', 'The record does not exist on the server.');
        if ($this->trashed($model)) {
            return ['status' => 'applied', 'version' => $model->version, 'already_deleted' => true];
        }
        $baseVersion = $c['base_version'] ?? throw new RejectChange('base_version_required', 'base_version is required for deletes.');

        if ($model->version !== $baseVersion) {
            [$changedSince] = $this->changesSince($c['entity'], $c['entity_id'], $baseVersion, $device);
            if ($changedSince !== []) {
                return $this->handlePolicyConflict($def, $c, $model, $baseVersion, 'updated_on_server', $changedSince, [], $actor);
            }
        }

        ($def->guard)?->__invoke($actor, 'delete', $model, []);
        $model->delete();

        return ['status' => 'applied', 'version' => $model->version];
    }

    private function write(EntityDefinition $def, Model $model, array $c, array $fields, User $actor, bool $merged): array
    {
        $fields = $this->validated($def, 'update', $model, $fields);
        $fields = $def->prepare ? ($def->prepare)('update', $model, $fields + ['id' => $model->getKey()]) : $fields;
        ($def->guard)?->__invoke($actor, 'update', $model, $fields);

        unset($fields['id']);
        $model->forceFill($fields)->save();

        return ['status' => 'applied', 'version' => $model->version, 'merged' => $merged] + ($model->wasChanged() ? [] : ['noop' => true]);
    }

    // ---- conflicts -----------------------------------------------------

    private function handlePolicyConflict(EntityDefinition $def, array $c, Model $model, int $base, string $reason, array $overlap, array $fields, User $actor): array
    {
        if ($def->conflictPolicy === EntityDefinition::POLICY_CLIENT_WINS && $reason === 'field_overlap') {
            $this->recordConflict($c, $model, $base, $reason, $overlap, $fields, 'resolved', 'client_wins');
            $result = $this->write($def, $model, $c, $fields, $actor, false);

            return $result + ['overwrote' => $overlap];
        }

        return $this->conflict($def, $c, $model, $base, $reason, $overlap, $fields, $actor);
    }

    private function conflict(EntityDefinition $def, array $c, Model $model, int $base, string $reason, array $overlap, array $fields, User $actor): array
    {
        $serverWins = $def->conflictPolicy === EntityDefinition::POLICY_SERVER_WINS;
        $record = $this->recordConflict($c, $model, $base, $reason, $overlap, $fields, $serverWins ? 'resolved' : 'open', $serverWins ? 'server_wins' : null);

        return [
            'status' => 'conflict',
            'code' => $reason,
            'conflict_id' => $record->id,
            'resolved' => $serverWins,
            'server_version' => $model->version,
            'conflicting_fields' => $overlap,
            'server' => $model->toSyncPayload(),
        ];
    }

    private function recordConflict(array $c, Model $model, int $base, string $reason, array $overlap, array $fields, string $status, ?string $resolution): SyncConflict
    {
        return SyncConflict::query()->firstOrCreate(['change_id' => $c['change_id']], [
            'entity' => $c['entity'],
            'entity_id' => $c['entity_id'],
            'op' => $c['op'],
            'device_id' => app(DeviceContext::class)->deviceId(),
            'user_id' => app(DeviceContext::class)->userId(),
            'base_version' => $base,
            'server_version' => $model->version,
            'reason' => $reason,
            'conflicting_fields' => $overlap,
            'local_payload' => $fields,
            'server_payload' => $model->toSyncPayload(),
            'status' => $status,
            'resolution' => $resolution,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);
    }

    /**
     * Fields changed (and whether a delete happened) since $baseVersion by OTHER devices.
     * The pushing device's own earlier changes are excluded: they are causally before this one
     * (they travel through the same ordered outbox), so they can never conflict with it.
     *
     * @return array{0:list<string>,1:bool}
     */
    private function changesSince(string $entity, string $id, int $base, Device $device): array
    {
        $rows = SyncChange::query()
            ->where('entity', $entity)->where('entity_id', $id)->where('version', '>', $base)
            ->where(fn ($q) => $q->whereNull('device_id')->orWhere('device_id', '!=', $device->getKey()))
            ->get();

        $fields = [];
        $deleted = false;
        foreach ($rows as $row) {
            if ($row->op === 'delete') {
                $deleted = true;
            }
            $fields = array_merge($fields, $row->fields ?? []);
        }

        return [array_values(array_unique($fields)), $deleted];
    }

    // ---- helpers -------------------------------------------------------

    /** Query including tombstones; append-only models (no soft delete) just query normally. */
    private function query(string $class): \Illuminate\Database\Eloquent\Builder
    {
        return $this->softDeletes($class) ? $class::withTrashed() : $class::query();
    }

    private function trashed(Model $model): bool
    {
        return $this->softDeletes($model::class) && $model->trashed();
    }

    private function softDeletes(string $class): bool
    {
        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true);
    }

    /** Strip server-controlled meta; reject anything not explicitly writable. */
    private function cleanFields(EntityDefinition $def, array $fields): array
    {
        $fields = array_diff_key($fields, array_flip(self::META));
        $unknown = array_values(array_diff(array_keys($fields), $def->writable));
        if ($unknown !== []) {
            throw new RejectChange('invalid_field', 'Field(s) not writable: '.implode(', ', $unknown), ['fields' => $unknown]);
        }

        return $fields;
    }

    private function validated(EntityDefinition $def, string $op, ?Model $existing, array $fields): array
    {
        $rules = $def->rulesFor($op, $existing, $fields);
        if ($rules === []) {
            return $fields;
        }
        // Only the fields the device sent are validated and applied.
        $rules = $op === 'update' ? array_intersect_key($rules, $fields) : $rules;
        Validator::make($fields, $rules)->validate();

        return $fields;
    }

    /** Outcome of a change we've already processed, if any. */
    private function priorOutcome(string $changeId): ?array
    {
        if ($feed = SyncChange::query()->where('change_id', $changeId)->first()) {
            return ['status' => 'duplicate', 'version' => $feed->version];
        }
        if ($conflict = SyncConflict::query()->where('change_id', $changeId)->first()) {
            return [
                'status' => 'conflict', 'code' => $conflict->reason, 'conflict_id' => $conflict->id,
                'resolved' => $conflict->status === 'resolved', 'server_version' => $conflict->server_version,
                'conflicting_fields' => $conflict->conflicting_fields, 'server' => $conflict->server_payload, 'duplicate' => true,
            ];
        }

        return null;
    }

    private function reject(string $code, string $message, array $errors = []): array
    {
        return ['status' => 'rejected', 'code' => $code, 'message' => $message] + ($errors ? ['errors' => $errors] : []);
    }
}
