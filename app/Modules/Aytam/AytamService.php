<?php

namespace App\Modules\Aytam;

use App\Core\Audit\Auditor;
use App\Models\Aytam;
use App\Models\Program;
use App\Models\User;
use App\Sync\RejectChange;
use Illuminate\Support\Facades\DB;

/** Creating, changing and moving Aytam records. The one place that knows how a record is born and how it changes state. */
class AytamService
{
    public function __construct(private AytamCodes $codes, private Auditor $auditor) {}

    /**
     * @param  array<string,mixed>  $data  already validated with AytamRules
     * @param  string  $status  draft, or a reviewer-only starting status
     */
    public function create(Program $program, array $data, User $actor, string $source = 'manual', ?string $registrationId = null, string $status = Aytam::DRAFT): Aytam
    {
        return DB::transaction(function () use ($program, $data, $actor, $source, $registrationId, $status) {
            $aytam = new Aytam;
            $aytam->forceFill(array_intersect_key($data, array_flip(AytamRules::fields())) + [
                'program_id' => $program->getKey(),
                'aytam_code' => $this->codes->next($program),
                'status' => $status,
                'source' => $source,
                'registration_id' => $registrationId,
            ]);
            if (in_array($status, [Aytam::APPROVED, Aytam::ACTIVE], true)) {
                $aytam->approved_at = now();
                $aytam->approved_by = $actor->getKey();
            }
            $aytam->save();

            return $aytam;
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Aytam $aytam, array $data): Aytam
    {
        $aytam->forceFill(array_intersect_key($data, array_flip(AytamRules::fields())))->save();

        return $aytam;
    }

    /** @throws RejectChange */
    public function transition(Aytam $aytam, Program $program, string $to, User $actor, ?string $note = null): Aytam
    {
        $from = $aytam->status;
        $required = AytamWorkflow::next($from)[$to] ?? throw new RejectChange('invalid_transition', "A {$from} record cannot become {$to}.");
        if (! $actor->hasPermission($required, $program)) {
            throw new RejectChange('forbidden', 'You do not have permission to make this change.');
        }
        if (AytamWorkflow::requiresReason($to) && trim((string) $note) === '') {
            throw new RejectChange('reason_required', 'Say what needs to be corrected.');
        }

        $aytam->status = $to;
        $aytam->status_note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
        if (in_array($to, [Aytam::APPROVED, Aytam::ACTIVE], true) && $aytam->approved_at === null) {
            $aytam->approved_at = now();
            $aytam->approved_by = $actor->getKey();
        }
        $aytam->save();

        $this->auditor->record('aytam.'.match ($to) {
            Aytam::APPROVED => 'approved', Aytam::NEEDS_CORRECTION => 'returned', Aytam::PENDING_REVIEW => 'submitted', default => 'status_changed',
        }, "{$aytam->auditLabel()}: {$from} -> {$to}".($aytam->status_note ? " ({$aytam->status_note})" : ''), 'aytam', $aytam->getKey(), ['status' => $from], ['status' => $to]);

        return $aytam;
    }
}
