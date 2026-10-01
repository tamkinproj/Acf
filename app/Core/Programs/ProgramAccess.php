<?php

namespace App\Core\Programs;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Who can see and manage which programs. One place, so lists, detail pages and module routes agree. */
class ProgramAccess
{
    public function canSeeAll(User $user): bool
    {
        return $user->holdsEverything() || $user->hasPermission('programs.view');
    }

    /** @return Builder<Program> */
    public function visibleTo(User $user): Builder
    {
        $query = Program::query();

        return $this->canSeeAll($user) ? $query : $query->whereIn('id', array_keys($user->programGrants()) ?: ['-']);
    }

    public function canView(User $user, Program $program): bool
    {
        return $this->canSeeAll($user) || array_key_exists($program->getKey(), $user->programGrants());
    }

    public function canManage(User $user, Program $program): bool
    {
        return $user->hasPermission('programs.update');
    }

    /** Foundation-level managers, or the module's own supervisor (the Aytam Mushrif configures Aytam partners). */
    public function canConfigure(User $user, Program $program): bool
    {
        if ($this->canManage($user, $program)) {
            return true;
        }
        $permission = $program->moduleDefinition()?->configurePermission();

        return $permission !== null && $user->hasPermission($permission, $program);
    }
}
