<?php

namespace App\Core\Platform;

use App\Core\Audit\Auditor;
use App\Core\Users\SessionRevoker;
use App\Models\Foundation;
use App\Models\User;
use App\Sync\RejectChange;
use App\Tenancy\TenantContext;

/** Foundation lifecycle controlled by the platform. */
class FoundationService
{
    public function __construct(private TenantContext $tenant, private Auditor $auditor, private SessionRevoker $sessions) {}

    /** Active / inactive / suspended. Anything but active locks the foundation's people out immediately. */
    public function setStatus(Foundation $foundation, string $status, ?string $reason, User $actor): Foundation
    {
        if (! in_array($status, Foundation::STATUSES, true)) {
            throw new RejectChange('invalid_status', 'Unknown status.');
        }
        if ($foundation->status === $status) {
            return $foundation;
        }
        $old = $foundation->status;
        $foundation->forceFill(['status' => $status, 'status_reason' => $reason, 'status_changed_at' => now()])->save();

        if ($status !== Foundation::ACTIVE) {
            $this->tenant->asSystem(function () use ($foundation) {
                User::query()->where('foundation_id', $foundation->getKey())->pluck('id')
                    ->each(fn ($id) => $this->sessions->revokeAll($id));
            });
        }
        $this->auditor->record("foundation.{$status}", "Foundation \"{$foundation->name}\" is now {$status}".($reason ? " ({$reason})" : ''),
            'foundations', $foundation->getKey(), ['status' => $old], ['status' => $status, 'reason' => $reason], $actor);
        $this->auditor->record('foundation.status_changed', "Foundation status changed from {$old} to {$status} by the platform", 'foundations', $foundation->getKey(),
            ['status' => $old], ['status' => $status], $actor, foundationId: $foundation->getKey());

        return $foundation->refresh();
    }
}
