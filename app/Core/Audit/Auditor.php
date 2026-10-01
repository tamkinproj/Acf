<?php

namespace App\Core\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Sync\DeviceContext;
use App\Sync\SyncRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the accountability trail. Unlike MuslimEdu's Auditable trait this does
 * NOT swallow failures: model writes call it inside their own transaction, so a
 * write that cannot be audited does not happen. Credentials and secrets never
 * reach it - models only expose their declared sync fields (minus $auditExcept).
 */
class Auditor
{
    private ?string $correlationId = null;
    private int $muted = 0;

    public function __construct(private DeviceContext $context, private SyncRegistry $registry) {}

    public function record(
        string $action,
        string $summary,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $old = null,
        ?array $new = null,
        ?User $actor = null,
        ?string $foundationId = null,
    ): AuditLog {
        if ($this->muted > 0) {
            return new AuditLog;
        }
        $actor ??= auth()->user();

        return AuditLog::create([
            'foundation_id' => $foundationId,
            'occurred_at' => now(),
            'device_id' => $this->context->deviceId(),
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => Str::limit($summary, 497),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'correlation_id' => $this->correlationId ??= (string) Str::uuid7(),
            'ip_address' => $this->context->ip(),
        ]);
    }

    /** Bulk set-up (provisioning a foundation) writes one summary entry instead of dozens of per-row ones. */
    public function muted(callable $fn): mixed
    {
        $this->muted++;
        try {
            return $fn();
        } finally {
            $this->muted--;
        }
    }

    public function forModel(Model $model, string $event, ?array $old, ?array $new): AuditLog
    {
        $entity = $this->registry->entityNameFor($model::class) ?? Str::snake(class_basename($model));
        $singular = Str::singular($entity);
        /** @var \App\Sync\Concerns\HasSyncMetadata $model */
        $label = str_replace('_', ' ', $singular);

        return $this->record(
            "{$singular}.{$event}",
            ucfirst($event)." {$label} \"{$model->auditLabel()}\"",
            $entity,
            (string) $model->getKey(),
            $old,
            $new,
        );
    }
}
