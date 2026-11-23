<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\AuditDescriber;
use App\Support\SystemAccounts;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['user_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:60'], 'area' => ['nullable', 'in:'.implode(',', AuditDescriber::AREAS)], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $logs = AuditLog::where('foundation_id', $request->user()->foundation_id)
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->query('area'), fn ($q, $v) => $q->whereIn('action', AuditDescriber::actionsIn($v)))
            // the System team's activity is not shown in the office: what they did, what was done to their accounts, and system-only work
            ->when(SystemAccounts::hiddenFrom($request->user()), function ($q) use ($request) {
                $ids = SystemAccounts::ids((int) $request->user()->foundation_id);
                if ($ids) {
                    $q->where(fn ($w) => $w->whereNull('user_id')->orWhereNotIn('user_id', $ids))
                        ->where(fn ($w) => $w->whereNull('subject_id')->orWhere('subject_type', '!=', 'user')->orWhereNotIn('subject_id', $ids));
                }
                foreach (SystemAccounts::ACTIONS as $prefix) {
                    $q->where('action', 'not like', $prefix.'%');
                }
            })
            ->orderByDesc('id')->paginate((int) $request->query('per_page', 50));

        $logs->setCollection(collect(AuditDescriber::many($logs->getCollection())));

        return ApiResponse::collection($logs);
    }
}
