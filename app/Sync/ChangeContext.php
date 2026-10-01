<?php

namespace App\Sync;

use Illuminate\Support\Str;

/**
 * Carries the client's idempotency key (change_id) from ChangeApplier down to
 * the model hook that writes the change feed row, so the feed row for a
 * client-originated change carries the client's own change_id.
 */
class ChangeContext
{
    private ?string $changeId = null;
    private ?string $clientTs = null;

    public function begin(string $changeId, ?string $clientTs = null): void
    {
        $this->changeId = $changeId;
        $this->clientTs = $clientTs;
    }

    public function end(): void
    {
        $this->changeId = $this->clientTs = null;
    }

    /** Consumed by the first feed row written; later rows in the same unit get fresh ids. */
    public function take(): string
    {
        $id = $this->changeId ?? (string) Str::uuid7();
        $this->changeId = null;

        return $id;
    }

    public function clientTs(): ?string
    {
        return $this->clientTs;
    }
}
