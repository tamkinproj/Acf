<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SyncConflict;
use App\Support\ApiResponse;
use App\Sync\ConflictResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConflictController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:open,resolved']]);
        $device = $request->attributes->get('device');

        $rows = SyncConflict::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when(! $request->user()->hasPermission('sync.manage'), fn ($q) => $q->where('device_id', $device->getKey()))
            ->latest('created_at')->limit(200)->get();

        return ApiResponse::ok($rows);
    }

    public function resolve(Request $request, SyncConflict $conflict, ConflictResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:accept_server,accept_local,merged'],
            'fields' => ['required_if:resolution,merged', 'nullable', 'array'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        $result = $resolver->resolve($conflict, $data['resolution'], $data['fields'] ?? null, $request->user(), $device);

        return in_array($result['status'], ['applied', 'duplicate'], true)
            ? ApiResponse::ok($conflict->fresh())
            : ApiResponse::error(strtoupper($result['code'] ?? 'CONFLICT_RESOLUTION_FAILED'), $result['message'] ?? 'Could not resolve the conflict.', 422, $result['errors'] ?? []);
    }
}
