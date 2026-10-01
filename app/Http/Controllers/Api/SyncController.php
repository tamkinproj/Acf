<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncChange;
use App\Models\SyncConflict;
use App\Support\ApiResponse;
use App\Sync\ChangeApplier;
use App\Sync\SyncRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The synchronization API. Kept apart from the UI endpoints: a client never
 * writes syncable entities any other way, online or offline.
 *
 *   POST /api/sync/push    device -> server   (idempotent, per-change results)
 *   GET  /api/sync/pull    server -> device   (cursor-based change feed)
 *   GET  /api/sync/status  connectivity probe + device/server state
 *   GET  /api/sync/schema  what this user's device may read and write
 */
class SyncController extends Controller
{
    public function __construct(private SyncRegistry $registry) {}

    public function push(Request $request, ChangeApplier $applier, Auditor $auditor): JsonResponse
    {
        $max = (int) config('foundation.sync.max_push_batch');
        $data = $request->validate([
            'changes' => ['required', 'array', "max:{$max}"],
            'changes.*' => ['array'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        $user = $request->user();

        $results = [];
        foreach ($data['changes'] as $change) {
            $results[] = $applier->apply($change, $user, $device);
        }

        $count = fn (string $s) => count(array_filter($results, fn ($r) => $r['status'] === $s));
        if ($results !== [] && $count('applied') + $count('conflict') + $count('rejected') > 0) {
            $auditor->record('sync.push',
                sprintf('Synchronized changes from %s: %d applied, %d conflicts, %d rejected', $device->name, $count('applied'), $count('conflict'), $count('rejected')),
                'devices', $device->getKey(), null,
                ['applied' => $count('applied'), 'duplicate' => $count('duplicate'), 'conflict' => $count('conflict'), 'rejected' => $count('rejected')],
            );
        }

        Device::withoutEvents(fn () => $device->forceFill(['last_seen_at' => now()])->save());

        return ApiResponse::ok(['results' => $results], ['server_time' => now()->toIso8601String(), 'latest_seq' => $this->settledMaxSeq()]);
    }

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('foundation.sync.max_pull_page_size')],
            'entities' => ['nullable', 'array'],
            'entities.*' => ['string'],
        ]);
        $since = (int) $data['since'];
        $limit = (int) ($data['limit'] ?? config('foundation.sync.pull_page_size'));
        $user = $request->user();
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $allowed = $this->registry->pullableFor($user);
        if (! empty($data['entities'])) {
            $allowed = array_values(array_intersect($allowed, $data['entities']));
        }

        $settledMax = $this->settledMaxSeq();

        $rows = SyncChange::query()
            ->where('seq', '>', $since)->where('seq', '<=', $settledMax)
            ->whereIn('entity', $allowed)
            ->orderBy('seq')->limit($limit + 1)->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $visible = $rows->filter(function (SyncChange $row) use ($user) {
            $vis = $this->registry->get($row->entity)?->visible;

            return $vis === null || $vis($user, $row);
        });

        $nextSeq = $hasMore ? (int) $rows->last()->seq : max($since, $settledMax);

        // The client confirms everything up to `since` is safely stored; remember it so tombstones
        // are only ever pruned once every active device has passed them.
        if ($since > $device->last_pull_seq) {
            Device::withoutEvents(fn () => $device->forceFill(['last_pull_seq' => $since, 'last_seen_at' => now()])->save());
        }

        return ApiResponse::ok([
            'changes' => $visible->values()->map(fn (SyncChange $r) => [
                'seq' => (int) $r->seq,
                'change_id' => $r->change_id,
                'entity' => $r->entity,
                'entity_id' => $r->entity_id,
                'op' => $r->op,
                'version' => $r->version,
                'device_id' => $r->device_id,
                'payload' => $r->payload,
            ])->all(),
            'next_seq' => $nextSeq,
            'has_more' => $hasMore,
        ], ['server_time' => now()->toIso8601String()]);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $user = $request->user();

        return ApiResponse::ok([
            'online' => true,
            'server_time' => now()->toIso8601String(),
            'app_version' => config('foundation.version'),
            'schema_version' => (int) config('foundation.schema_version'),
            'latest_seq' => $this->settledMaxSeq(),
            'device' => [
                'id' => $device->getKey(), 'code' => $device->device_code, 'name' => $device->name, 'type' => $device->type,
                'last_pull_seq' => $device->last_pull_seq,
            ],
            'open_conflicts' => SyncConflict::query()->where('status', 'open')
                ->when(! $user->hasPermission('sync.manage'), fn ($q) => $q->where('device_id', $device->getKey()))->count(),
        ]);
    }

    public function schema(Request $request): JsonResponse
    {
        $user = $request->user();
        $entities = [];
        foreach ($this->registry->all() as $name => $def) {
            $entities[$name] = [
                'model_fields' => ($def->model)::syncFields(),
                'meta_fields' => ($def->model)::syncMetaFields(),
                'writable' => $def->writable,
                'soft_deletes' => in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($def->model), true),
                'can_pull' => $def->canPull($user),
                'ops' => collect(['create', 'update', 'delete'])->mapWithKeys(fn ($op) => [$op => $def->canWrite($user, $op)])->all(),
                'conflict_policy' => $def->conflictPolicy,
            ];
        }

        return ApiResponse::ok(['schema_version' => (int) config('foundation.schema_version'), 'entities' => $entities]);
    }

    /** Highest `seq` old enough to be safe to hand out (see foundation.sync.settle_seconds). */
    private function settledMaxSeq(): int
    {
        $cutoff = now()->subSeconds((int) config('foundation.sync.settle_seconds'));

        return (int) SyncChange::query()->where('created_at', '<=', $cutoff)->max('seq');
    }
}
