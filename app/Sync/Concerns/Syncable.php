<?php

namespace App\Sync\Concerns;

use App\Core\Audit\Auditor;
use App\Sync\ChangeFeed;
use App\Sync\DeviceContext;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * HasSyncMetadata + soft deletion whose tombstone is itself versioned and
 * replicated (the stock SoftDeletes update would skip the version bump).
 */
trait Syncable
{
    use HasSyncMetadata, SoftDeletes;

    protected function runSoftDelete()
    {
        DB::transaction(function () {
            $ctx = app(DeviceContext::class);
            $time = $this->freshTimestamp();

            $this->version = (int) $this->version + 1;
            $this->deleted_by = $ctx->userId();
            $this->origin_device_id = $ctx->deviceId() ?? $this->origin_device_id;
            $this->{$this->getDeletedAtColumn()} = $time;
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns = [];
            foreach (['version', 'deleted_by', 'origin_device_id', $this->getDeletedAtColumn(), $this->getUpdatedAtColumn()] as $c) {
                $columns[$c] = $this->getAttributes()[$c] instanceof \DateTimeInterface
                    ? $this->fromDateTime($this->getAttributes()[$c])
                    : $this->getAttributes()[$c];
            }

            $this->setKeysForSaveQuery($this->newModelQuery())->update($columns);
            $this->syncOriginalAttributes(array_keys($columns));

            app(ChangeFeed::class)->record($this, 'delete');
            if ($this->isAudited()) {
                app(Auditor::class)->forModel($this, 'deleted', $this->auditValues(static::syncFields()), null);
            }
        });

        $this->fireModelEvent('trashed', false);
    }
}
