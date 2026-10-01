<?php

namespace App\Sync\Concerns;

use App\Core\Audit\Auditor;
use App\Sync\ChangeFeed;
use App\Sync\DeviceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Makes a model replicable.
 *
 *  - UUIDv7 primary key (time-ordered, generated on any device, collision-safe)
 *  - `version` bumps by exactly 1 per accepted change that touches a synced field
 *  - origin device / acting user stamping
 *  - every create / update / delete also writes the change feed and the audit
 *    log, atomically with the write itself
 *
 * A model declares which fields replicate via syncFields(). Anything not
 * listed (password hashes, storage paths...) never appears in a sync payload.
 */
trait HasSyncMetadata
{
    use HasUuids;

    /** Business fields that replicate. Override in the model. */
    abstract public static function syncFields(): array;

    /** Short human label for audit summaries, e.g. a location's name. */
    public function auditLabel(): string
    {
        return (string) ($this->name ?? $this->getKey());
    }

    /** Set false on models that must not be audited (the audit log itself). */
    public function isAudited(): bool
    {
        return true;
    }

    public static function bootHasSyncMetadata(): void
    {
        static::creating(function ($model) {
            $ctx = app(DeviceContext::class);
            $model->version = 1;
            $model->origin_device_id ??= $ctx->deviceId();
            $model->created_by ??= $ctx->userId();
            $model->updated_by ??= $ctx->userId();
        });

        static::updating(function ($model) {
            $dirty = array_keys($model->getDirty());
            $replicable = array_intersect($dirty, static::syncFields());
            // Changes to server-local columns (last_login_at, tokens...) must not bump the
            // replication version, or devices would see phantom conflicts.
            if ($replicable === [] && ! in_array('deleted_at', $dirty, true)) {
                return;
            }
            $ctx = app(DeviceContext::class);
            $model->version = (int) $model->getOriginal('version') + 1;
            $model->updated_by = $ctx->userId();
            $model->origin_device_id = $ctx->deviceId() ?? $model->origin_device_id;
        });

        static::created(function ($model) {
            // Columns the database defaults (is_active, status...) are absent from the in-memory model;
            // load them so the feed and audit describe the row as it truly is, not as it was constructed.
            if ($fresh = $model->newQueryWithoutScopes()->find($model->getKey())) {
                $model->setRawAttributes($model->getAttributes() + array_diff_key($fresh->getAttributes(), $model->getAttributes()), true);
            }
            app(ChangeFeed::class)->record($model, 'create');
            if ($model->isAudited()) {
                app(Auditor::class)->forModel($model, 'created', null, $model->auditValues(static::syncFields()));
            }
        });

        static::updated(function ($model) {
            if (! $model->wasChanged('version')) {
                return;
            }
            $changed = array_values(array_intersect(array_keys($model->getChanges()), static::syncFields()));
            app(ChangeFeed::class)->record($model, 'update', $changed);
            if ($model->isAudited() && $changed !== []) {
                $old = array_intersect_key($model->getOriginal(), array_flip($changed));
                app(Auditor::class)->forModel($model, 'updated', $model->auditRaw($old), $model->auditValues($changed));
            }
        });

        // Replicated rows are tombstoned, never removed: a hard delete would be invisible to
        // devices that have not synced yet.
        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(function () {
                throw new LogicException('Synchronized records cannot be hard-deleted; use soft delete.');
            });
        }
    }

    // ---- insert/update inside one transaction with their feed + audit rows -----

    protected function performInsert(Builder $query)
    {
        return DB::transaction(fn () => parent::performInsert($query));
    }

    protected function performUpdate(Builder $query)
    {
        return DB::transaction(fn () => parent::performUpdate($query));
    }

    // ---- payloads ---------------------------------------------------------

    /** @return list<string> */
    public static function syncMetaFields(): array
    {
        $meta = ['id', 'version', 'created_at', 'updated_at', 'origin_device_id', 'created_by', 'updated_by'];
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $meta[] = 'deleted_at';
        }

        return $meta;
    }

    /**
     * @param  list<string>|null  $only  limit business fields (meta fields are always included)
     * @return array<string,mixed>
     */
    public function toSyncPayload(?array $only = null): array
    {
        $business = $only === null ? static::syncFields() : array_values(array_intersect($only, static::syncFields()));
        $payload = [];
        foreach (array_unique(array_merge(static::syncMetaFields(), $business)) as $field) {
            $payload[$field] = $this->syncValue($field);
        }

        return $payload;
    }

    private function syncValue(string $field): mixed
    {
        $value = $this->getAttribute($field);

        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s.v\Z')
            : $value;
    }

    /** @param list<string> $fields */
    public function auditValues(array $fields): array
    {
        $except = property_exists($this, 'auditExcept') ? $this->auditExcept : [];
        $out = [];
        foreach (array_diff($fields, $except) as $field) {
            $out[$field] = $this->syncValue($field);
        }

        return $out;
    }

    public function auditRaw(array $values): array
    {
        $except = property_exists($this, 'auditExcept') ? $this->auditExcept : [];

        return array_diff_key($values, array_flip($except));
    }
}
