<?php

namespace App\Modules\Aytam;

use App\Models\Aytam;
use App\Models\AytamAssignment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which Aytam records a person may open. Someone with "view all" sees the whole program; everyone else sees only the
 * records assigned to them. Enforced in the query, so a record outside the set is simply "not found" everywhere.
 */
class AytamAccess
{
    public function seesAll(User $user, Program $program): bool
    {
        return $user->hasPermission('aytam.view_all', $program);
    }

    /** @return Builder<Aytam> */
    public function visible(User $user, Program $program): Builder
    {
        $query = Aytam::query()->where('program_id', $program->getKey());

        return $this->seesAll($user, $program)
            ? $query
            : $query->whereIn('id', AytamAssignment::query()->where('program_id', $program->getKey())->where('user_id', $user->getKey())->select('aytam_id'));
    }

    public function find(User $user, Program $program, string $id): Aytam
    {
        return $this->visible($user, $program)->findOrFail($id);
    }

    /** Fields this person may change: everything with "view all", otherwise contact, address and education only. */
    public function editableFields(User $user, Program $program): array
    {
        return $this->seesAll($user, $program) ? AytamRules::fields() : Aytam::FIELD_EDITABLE;
    }
}
