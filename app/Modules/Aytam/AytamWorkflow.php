<?php

namespace App\Modules\Aytam;

use App\Models\Aytam;
use App\Models\Program;
use App\Models\User;

/**
 * Where an Aytam record may go next, and who may send it there. Submitting work for review needs only the right to edit;
 * deciding (approve, return, activate, deactivate, archive) is the reviewer's.
 */
final class AytamWorkflow
{
    private const SUBMIT = 'aytam.update';
    private const DECIDE = 'aytam.review';

    /** from => [to => permission] */
    private const TRANSITIONS = [
        Aytam::DRAFT => [Aytam::PENDING_REVIEW => self::SUBMIT, Aytam::APPROVED => self::DECIDE, Aytam::ACTIVE => self::DECIDE, Aytam::ARCHIVED => self::DECIDE],
        Aytam::PENDING_REVIEW => [Aytam::DRAFT => self::SUBMIT, Aytam::APPROVED => self::DECIDE, Aytam::NEEDS_CORRECTION => self::DECIDE],
        Aytam::NEEDS_CORRECTION => [Aytam::DRAFT => self::SUBMIT, Aytam::PENDING_REVIEW => self::SUBMIT, Aytam::ARCHIVED => self::DECIDE],
        Aytam::APPROVED => [Aytam::ACTIVE => self::DECIDE, Aytam::INACTIVE => self::DECIDE, Aytam::NEEDS_CORRECTION => self::DECIDE],
        Aytam::ACTIVE => [Aytam::INACTIVE => self::DECIDE, Aytam::ARCHIVED => self::DECIDE],
        Aytam::INACTIVE => [Aytam::ACTIVE => self::DECIDE, Aytam::ARCHIVED => self::DECIDE],
        Aytam::ARCHIVED => [Aytam::INACTIVE => self::DECIDE],
    ];

    /** Statuses that count a record as part of the live caseload. */
    public const LIVE = [Aytam::DRAFT, Aytam::PENDING_REVIEW, Aytam::NEEDS_CORRECTION, Aytam::APPROVED, Aytam::ACTIVE];

    /** @return array<string,string> next status => permission key, for ALL moves from $from */
    public static function next(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /** @return list<string> the moves $user may make from $from in $program */
    public static function allowedFor(User $user, Program $program, string $from): array
    {
        return array_keys(array_filter(self::next($from), fn ($permission) => $user->hasPermission($permission, $program)));
    }

    public static function requiresReason(string $to): bool
    {
        return in_array($to, [Aytam::NEEDS_CORRECTION], true);
    }
}
