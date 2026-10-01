<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\Aytam;
use App\Models\Family;
use App\Modules\Aytam\AytamRules;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Reusable family records: store the household once, link every child to it. Needs "view all" - field workers see a family through their assigned child. */
class FamilyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $families = Family::query()->where('program_id', $program->getKey())->withCount('members')->with('guardian:id,full_name')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('father_name', 'like', "%{$t}%")->orWhere('mother_name', 'like', "%{$t}%")))
            ->orderBy('name')->limit(200)->get();

        return ApiResponse::ok($families->map(fn (Family $f) => $this->brief($f))->all());
    }

    public function show(Request $request, string $program, string $family): JsonResponse
    {
        $record = Family::query()->where('program_id', $request->attributes->get('program')->getKey())->with('guardian')->findOrFail($family);
        $members = Aytam::query()->where('family_id', $record->getKey())->orderBy('date_of_birth')->get()
            ->map(fn (Aytam $a) => ['id' => $a->id, 'aytam_code' => $a->aytam_code, 'name' => $a->fullName(), 'status' => $a->status, 'date_of_birth' => $a->date_of_birth?->toDateString()]);

        return ApiResponse::ok($this->full($record) + ['members' => $members->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $family = new Family;
        $family->forceFill($request->validate(AytamRules::familyRules($program, true)) + ['program_id' => $program->getKey()])->save();

        return ApiResponse::created($this->full($family->refresh()->load('guardian')));
    }

    public function update(Request $request, string $program, string $family): JsonResponse
    {
        $prog = $request->attributes->get('program');
        $record = Family::query()->where('program_id', $prog->getKey())->findOrFail($family);
        $rules = array_intersect_key(AytamRules::familyRules($prog, false), $request->all());
        $record->forceFill(Validator::make($request->only(array_keys($rules)), $rules)->validate())->save();

        return ApiResponse::ok($this->full($record->refresh()->load('guardian')));
    }

    public function destroy(Request $request, string $program, string $family): JsonResponse
    {
        $record = Family::query()->where('program_id', $request->attributes->get('program')->getKey())->findOrFail($family);
        if (Aytam::query()->where('family_id', $record->getKey())->exists()) {
            return ApiResponse::error('IN_USE', 'Children are still linked to this family. Move them first.', 409);
        }
        $record->delete();

        return ApiResponse::ok(null);
    }

    private function brief(Family $f): array
    {
        return ['id' => $f->id, 'name' => $f->name, 'father_name' => $f->father_name, 'mother_name' => $f->mother_name, 'city' => $f->city,
            'members_count' => $f->members_count ?? null, 'guardian' => $f->guardian ? ['id' => $f->guardian->id, 'full_name' => $f->guardian->full_name] : null];
    }

    private function full(Family $f): array
    {
        return $this->brief($f) + collect($f->only(['father_status', 'mother_status', 'phone', 'country', 'region', 'province', 'barangay', 'address_detail', 'notes', 'guardian_id']))->all()
            + ['version' => $f->version];
    }
}
