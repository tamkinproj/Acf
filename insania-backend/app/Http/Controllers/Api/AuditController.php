<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['user_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:60'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $logs = AuditLog::where('foundation_id', $request->user()->foundation_id)
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', $v))
            ->orderByDesc('id')->paginate((int) $request->query('per_page', 50));

        return ApiResponse::collection($logs);
    }
}
