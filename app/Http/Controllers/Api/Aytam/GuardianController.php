<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\Aytam;
use App\Models\Family;
use App\Models\Guardian;
use App\Modules\Aytam\AytamRules;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $program = $request->attributes->get('program');

        $rows = Guardian::query()->where('program_id', $program->getKey())
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('full_name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")))
            ->orderBy('full_name')->limit(200)->get();

        return ApiResponse::ok($rows->map(fn (Guardian $g) => $this->present($g))->all());
    }

    public function show(Request $request, string $program, string $guardian): JsonResponse
    {
        $g = Guardian::query()->where('program_id', $request->attributes->get('program')->getKey())->findOrFail($guardian);
        $families = Family::query()->where('guardian_id', $g->getKey())->get(['id', 'name']);
        $children = Aytam::query()->where(fn ($q) => $q->where('guardian_id', $g->getKey())->orWhereIn('family_id', $families->pluck('id')))->orderBy('first_name')->get()
            ->map(fn (Aytam $a) => ['id' => $a->id, 'aytam_code' => $a->aytam_code, 'name' => $a->fullName(), 'status' => $a->status]);

        return ApiResponse::ok($this->present($g) + ['families' => $families->all(), 'children' => $children->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $g = new Guardian;
        $g->forceFill($request->validate(AytamRules::guardianRules(true)) + ['program_id' => $program->getKey()])->save();

        return ApiResponse::created($this->present($g->refresh()));
    }

    public function update(Request $request, string $program, string $guardian): JsonResponse
    {
        $g = Guardian::query()->where('program_id', $request->attributes->get('program')->getKey())->findOrFail($guardian);
        $rules = array_intersect_key(AytamRules::guardianRules(false), $request->all());
        $g->forceFill(Validator::make($request->only(array_keys($rules)), $rules)->validate())->save();

        return ApiResponse::ok($this->present($g->refresh()));
    }

    public function destroy(Request $request, string $program, string $guardian): JsonResponse
    {
        $g = Guardian::query()->where('program_id', $request->attributes->get('program')->getKey())->findOrFail($guardian);
        if (Aytam::query()->where('guardian_id', $g->getKey())->exists() || Family::query()->where('guardian_id', $g->getKey())->exists()) {
            return ApiResponse::error('IN_USE', 'This guardian is still linked to children or families.', 409);
        }
        $g->delete();

        return ApiResponse::ok(null);
    }

    private function present(Guardian $g): array
    {
        return ['id' => $g->id, 'full_name' => $g->full_name, 'relationship' => $g->relationship, 'phone' => $g->phone, 'email' => $g->email, 'address' => $g->address, 'notes' => $g->notes, 'version' => $g->version];
    }
}
