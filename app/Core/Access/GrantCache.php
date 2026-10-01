<?php

namespace App\Core\Access;

/**
 * Per-process cache of "which program roles does this person hold", dropped the moment a membership or a role
 * changes - so a permission checked twice in one request costs one query, and never goes stale.
 */
final class GrantCache
{
    /** @var array<string,array<string,list<string>>> */
    private array $grants = [];

    /** @param callable():array<string,list<string>> $load */
    public function for(string $userId, callable $load): array
    {
        return $this->grants[$userId] ??= $load();
    }

    public function forget(?string $userId = null): void
    {
        if ($userId === null) {
            $this->grants = [];
        } else {
            unset($this->grants[$userId]);
        }
    }
}
