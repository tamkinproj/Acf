<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Programs\ProgramAccess;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramUser;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Who works in a program, and in which program role (Aytam Mushrif, Aytam Field Worker, ...). */
class ProgramTeamController extends Controller
{
    public function __construct(private ProgramAccess $access, private Auditor $auditor) {}

    public function index(Request $request, Program $program): JsonResponse
    {
        abort_unless($this->access->canView($request->user(), $program), 404);

        $members = ProgramUser::query()->where('program_id', $program->getKey())->with(['user:id,name,email,status', 'role:id,key,name'])->get()
            ->sortBy(fn (ProgramUser $m) => $m->user?->name)->values();

        $canManage = $this->access->canManage($request->user(), $program);
        // People who could be added: active, and not already on the team. Only offered to those who can add them.
        $candidates = $canManage
            ? User::query()->where('status', 'active')->whereNotIn('id', ProgramUser::query()->where('program_id', $program->getKey())->select('user_id'))->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return ApiResponse::ok([
            'can_manage' => $canManage,
            'candidates' => $candidates->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all(),
            'members' => $members->map(fn (ProgramUser $m) => [
                'user_id' => $m->user_id, 'name' => $m->user?->name, 'email' => $m->user?->email, 'user_status' => $m->user?->status,
                'role' => $m->role ? ['id' => $m->role->id, 'key' => $m->role->key, 'name' => $m->role->name] : null,
            ])->all(),
            'roles' => $this->assignableRoles($program)->map(fn (Role $r) => ['id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'description' => $r->description])->all(),
        ]);
    }

    /** Give someone a role in this program, or change it. */
    public function upsert(Request $request, Program $program, string $user): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'uuid', TenantRule::exists(Role::class, 'id', fn ($q) => $q->whereIn('id', $this->assignableRoles($program)->pluck('id')))],
        ]);
        $member = User::query()->where('status', 'active')->findOrFail($user);
        $role = Role::query()->findOrFail($data['role_id']);

        $membership = ProgramUser::query()->where('program_id', $program->getKey())->where('user_id', $member->getKey())->first()
            ?? new ProgramUser(['program_id' => $program->getKey(), 'user_id' => $member->getKey()]);
        $created = ! $membership->exists;
        $membership->role_id = $role->getKey();
        $membership->save();

        return ApiResponse::ok(['user_id' => $member->getKey(), 'role' => ['id' => $role->id, 'key' => $role->key, 'name' => $role->name]], status: $created ? 201 : 200);
    }

    public function destroy(Program $program, string $user): JsonResponse
    {
        $membership = ProgramUser::query()->where('program_id', $program->getKey())->where('user_id', $user)->with('user:id,name')->firstOrFail();
        $label = $membership->auditLabel();
        $membership->delete();
        $this->auditor->record('program_user.removed', "Removed {$label}", 'program_users', $membership->getKey());

        return ApiResponse::ok(null);
    }

    /** A program accepts its module's roles (and foundation-made program roles for that module or for any module). */
    private function assignableRoles(Program $program)
    {
        return Role::query()->where('scope', 'program')
            ->where(fn ($q) => $q->whereNull('module')->orWhere('module', $program->module))
            ->orderBy('name')->get();
    }
}
