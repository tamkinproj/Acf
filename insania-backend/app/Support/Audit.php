<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/** One line per sensitive action. Holds ids and short labels only, never names, numbers or documents. */
final class Audit
{
    public static function record(string $action, ?Request $request = null, ?string $subjectType = null, ?int $subjectId = null, array $meta = [], ?int $userId = null, ?int $foundationId = null): void
    {
        $user = $request?->user();

        AuditLog::create([
            'foundation_id' => $foundationId ?? $user?->foundation_id,
            'user_id' => $userId ?? $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta ?: null,
            'ip' => $request?->ip(),
        ]);
    }
}
