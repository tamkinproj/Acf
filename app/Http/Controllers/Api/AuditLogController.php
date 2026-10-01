<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:64'], 'user_id' => ['nullable', 'uuid'], 'device_id' => ['nullable', 'uuid'],
            'subject_type' => ['nullable', 'string', 'max:64'], 'subject_id' => ['nullable', 'string', 'max:36'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $page = AuditLog::query()
            ->when($request->action, fn ($q, $v) => str_contains($v, '.') ? $q->where('action', $v) : $q->where('action', 'like', $v.'.%'))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->device_id, fn ($q, $v) => $q->where('device_id', $v))
            ->when($request->subject_type, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($request->subject_id, fn ($q, $v) => $q->where('subject_id', $v))
            ->when($request->from, fn ($q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('occurred_at', '<=', $v))
            ->when($request->q, fn ($q, $v) => $q->where('summary', 'like', "%{$v}%"))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 50));

        $devices = Device::withTrashed()->whereIn('id', $page->pluck('device_id')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($page->getCollection()->map(fn (AuditLog $l) => [
            'id' => $l->id, 'occurred_at' => $l->occurred_at?->toIso8601String(), 'action' => $l->action,
            'summary' => $l->summary, 'user_id' => $l->user_id, 'user_name' => $l->user_name,
            'device_id' => $l->device_id, 'device_name' => $devices[$l->device_id] ?? null,
            'subject_type' => $l->subject_type, 'subject_id' => $l->subject_id,
            'old_values' => $l->old_values, 'new_values' => $l->new_values,
            // Everything stored on the server has, by definition, reached the server.
            'sync_status' => 'synced',
        ])->all(), ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }
}
