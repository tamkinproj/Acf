<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Platform activity: foundation lifecycle, platform sign-ins, platform settings. Foundations' own logs stay theirs. */
class PlatformActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['action' => ['nullable', 'string', 'max:64'], 'q' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $page = AuditLog::query()
            ->when($request->action, fn ($q, $v) => str_contains($v, '.') ? $q->where('action', $v) : $q->where('action', 'like', $v.'.%'))
            ->when($request->q, fn ($q, $v) => $q->where('summary', 'like', "%{$v}%"))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate((int) $request->input('per_page', 50));

        return ApiResponse::ok($page->getCollection()->map(fn (AuditLog $l) => [
            'id' => $l->id, 'occurred_at' => $l->occurred_at?->toIso8601String(), 'action' => $l->action, 'summary' => $l->summary,
            'user_name' => $l->user_name, 'subject_type' => $l->subject_type, 'subject_id' => $l->subject_id,
        ])->all(), ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }
}
