<?php

namespace App\Sync;

use App\Core\Audit\Auditor;
use App\Models\Device;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves a recorded conflict. Nothing is lost on either side: "accept_server"
 * keeps the server value, "accept_local"/"merged" re-apply the preserved local
 * change (or an edited version of it) on top of the CURRENT server version,
 * through the normal applier so validation and permissions still apply.
 */
class ConflictResolver
{
    public function __construct(private ChangeApplier $applier, private SyncRegistry $registry, private Auditor $auditor) {}

    /**
     * @param  array<string,mixed>|null  $mergedFields  required for 'merged'
     * @return array<string,mixed> applier-style result
     */
    public function resolve(SyncConflict $conflict, string $resolution, ?array $mergedFields, User $actor, Device $device): array
    {
        if (! $conflict->isOpen()) {
            return ['status' => 'rejected', 'code' => 'already_resolved', 'message' => 'This conflict is already resolved.'];
        }

        $result = ['status' => 'applied'];
        if ($resolution !== 'accept_server') {
            $def = $this->registry->get($conflict->entity);
            $class = $def?->model;
            $current = $class ? (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true) ? $class::withTrashed() : $class::query())->find($conflict->entity_id) : null;
            if (! $def || ! $current) {
                return ['status' => 'rejected', 'code' => 'not_found', 'message' => 'The record no longer exists.'];
            }

            $fields = $resolution === 'merged' ? ($mergedFields ?? []) : ($conflict->local_payload ?? []);
            if (method_exists($current, 'trashed') && $current->trashed() && $conflict->op !== 'delete') {
                return ['status' => 'rejected', 'code' => 'deleted_on_server', 'message' => 'The record was deleted on the server; restoring is not supported in Phase 1. Choose accept_server.'];
            }
            $result = $this->applier->apply([
                'change_id' => (string) Str::uuid7(),
                'entity' => $conflict->entity,
                'entity_id' => $conflict->entity_id,
                'op' => $conflict->op,
                'base_version' => $current->version,
                'fields' => $fields,
            ], $actor, $device);

            if (! in_array($result['status'], ['applied', 'duplicate'], true)) {
                return $result;
            }
        }

        $conflict->forceFill([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_by' => $actor->getKey(),
            'resolved_at' => now(),
        ])->save();

        $this->auditor->record('sync.conflict_resolved', "Resolved sync conflict on {$conflict->entity} ({$resolution})",
            $conflict->entity, $conflict->entity_id, null, ['resolution' => $resolution], $actor);

        return $result;
    }
}
