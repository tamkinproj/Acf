<?php

namespace App\Modules\Aytam\Registration;

use RuntimeException;

/** Approval stopped because the child may already be on file; a person must say what to do. */
class DuplicatesFound extends RuntimeException
{
    /** @param list<array<string,mixed>> $matches */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('This child may already be registered. Choose whether to use the existing record or create a new one.');
    }
}
