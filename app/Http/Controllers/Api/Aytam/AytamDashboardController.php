<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Aytam;
use App\Models\Document;
use App\Models\Program;
use App\Modules\Aytam\AytamAccess;
use App\Modules\Aytam\AytamWorkflow;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AytamDashboardController extends Controller
{
    public function __construct(private AytamAccess $access) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Program $program */
        $program = $request->attributes->get('program');
        $user = $request->user();
        $visible = fn () => $this->access->visible($user, $program);

        $byStatus = $visible()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $required = array_values($program->config['required_documents'] ?? []);

        $missing = 0;
        if ($required !== []) {
            $live = $visible()->whereIn('status', AytamWorkflow::LIVE)->pluck('id');
            $complete = Document::query()->whereIn('aytam_id', $live)->where('is_current', true)->where('verification_status', '!=', Document::REJECTED)
                ->whereIn('type', $required)->select('aytam_id', DB::raw('count(distinct type) as c'))->groupBy('aytam_id')->get()
                ->filter(fn ($r) => (int) $r->c >= count($required))->count();
            $missing = $live->count() - $complete;
        }

        $pendingRegistrations = $user->hasPermission('aytam.review', $program) && class_exists(\App\Models\Registration::class)
            ? \App\Models\Registration::query()->where('program_id', $program->getKey())->whereIn('status', ['pending_review'])->count() : null;

        $activity = AuditLog::query()->whereIn('subject_type', ['aytam', 'families', 'guardians', 'documents', 'registrations'])
            ->whereIn('subject_id', $visible()->select('id'))->orderByDesc('occurred_at')->limit(8)->get()
            ->map(fn ($l) => ['id' => $l->id, 'occurred_at' => $l->occurred_at?->toIso8601String(), 'action' => $l->action, 'summary' => $l->summary, 'user_name' => $l->user_name]);

        return ApiResponse::ok([
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[Aytam::ACTIVE] ?? 0),
            'pending_review' => (int) ($byStatus[Aytam::PENDING_REVIEW] ?? 0),
            'needs_correction' => (int) ($byStatus[Aytam::NEEDS_CORRECTION] ?? 0),
            'draft' => (int) ($byStatus[Aytam::DRAFT] ?? 0),
            'approved' => (int) ($byStatus[Aytam::APPROVED] ?? 0),
            'inactive' => (int) ($byStatus[Aytam::INACTIVE] ?? 0),
            'missing_documents' => $missing,
            'pending_registrations' => $pendingRegistrations,
            'sees_all' => $this->access->seesAll($user, $program),
            'recent_activity' => $activity->all(),
        ]);
    }
}
