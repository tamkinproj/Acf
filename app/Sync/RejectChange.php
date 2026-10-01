<?php

namespace App\Sync;

/** Thrown by entity guards/hooks to permanently reject a pushed change with a machine-readable code. */
class RejectChange extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '', public readonly array $errors = [])
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
