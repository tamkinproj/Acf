<?php

namespace App\Http\Middleware;

use App\Models\Program;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a program module's routes: ->middleware('program:aytam,aytam.view').
 *
 *  - the program must exist in the user's foundation and belong to that module (anything else is simply "not found")
 *  - changes need an ACTIVE program; archived programs are read-only history
 *  - the user needs one of the listed permissions inside THIS program (their foundation role, or their program role);
 *    "program:aytam,a+b,c" means (a AND b) OR c
 */
class ProgramContext
{
    public function handle(Request $request, Closure $next, string $module, string ...$permissions): Response
    {
        $given = $request->route('program');
        $program = $given instanceof Program ? $given : Program::query()->find((string) $given);
        if (! $program || $program->module !== $module) {
            return ApiResponse::error('NOT_FOUND', 'Not found.', 404);
        }

        $user = $request->user();
        // Separate arguments are alternatives (any one will do); "a+b" inside one argument means all of them are needed.
        $allowed = $permissions === [];
        foreach ($permissions as $alternative) {
            $allowed = $allowed || collect(explode('+', $alternative))->every(fn ($permission) => $user->hasPermission($permission, $program));
        }
        if (! $allowed) {
            return ApiResponse::error('FORBIDDEN', 'You do not have permission to do this.', 403);
        }

        if (! $request->isMethodSafe()) {
            if ($program->status === Program::ARCHIVED) {
                return ApiResponse::error('PROGRAM_ARCHIVED', 'This program is archived and can no longer be changed.', 409);
            }
            if ($program->status !== Program::ACTIVE) {
                return ApiResponse::error('PROGRAM_NOT_ACTIVE', 'Activate this program before making changes to it.', 409);
            }
        }

        $request->attributes->set('program', $program);

        return $next($request);
    }
}
