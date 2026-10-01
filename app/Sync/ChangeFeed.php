<?php

namespace App\Sync;

use App\Models\SyncChange;
use Illuminate\Database\Eloquent\Model;

/** Writes the replication change feed. Called from the model hooks, inside the write's transaction. */
class ChangeFeed
{
    public function __construct(
        private SyncRegistry $registry,
        private DeviceContext $context,
        private ChangeContext $change,
    ) {}

    /** @param list<string> $changedFields business fields touched (for update) */
    public function record(Model $model, string $op, array $changedFields = []): ?SyncChange
    {
        $entity = $this->registry->entityNameFor($model::class);
        if ($entity === null) {
            return null;
        }

        /** @var \App\Sync\Concerns\HasSyncMetadata $model */
        $payload = match ($op) {
            'create' => $model->toSyncPayload(),
            'update' => $model->toSyncPayload(array_merge($changedFields, ['deleted_at'])),
            'delete' => $model->toSyncPayload(['deleted_at']),
        };

        return SyncChange::create([
            'change_id' => $this->change->take(),
            'entity' => $entity,
            'entity_id' => $model->getKey(),
            'op' => $op,
            'version' => $model->version,
            'fields' => $op === 'update' ? array_values($changedFields) : null,
            'payload' => $payload,
            'device_id' => $this->context->deviceId(),
            'user_id' => $this->context->userId(),
            'client_ts' => $this->change->clientTs(),
            'created_at' => now(),
        ]);
    }
}
