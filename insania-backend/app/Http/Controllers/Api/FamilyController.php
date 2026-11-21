<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/** What a family account sees: its own children only, and never bank, money or other families. */
class FamilyController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (! $user->guardian_id && ! $user->child_id) {
            return ApiResponse::collection([]);
        }

        // a family account sees its guardian's children; an orphan's own account sees that orphan
        $children = Child::query()->with('documents')->where(function ($w) use ($user) {
            if ($user->guardian_id) {
                $w->orWhere('guardian_id', $user->guardian_id);
            }
            if ($user->child_id) {
                $w->orWhere('id', $user->child_id);
            }
        })->orderBy('ref')->get()->map(fn (Child $c) => [
            'id' => $c->id, 'ref' => $c->ref, 'external_no' => $c->external_no, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar,
            'grade' => $c->grade, 'madrasa_grade' => $c->madrasa_grade, 'age' => $c->birth_date?->age, 'status' => $c->status, 'is_self' => $user->child_id === $c->id,
            'documents' => collect(Child::DOCUMENT_TYPES)->map(fn ($t) => [
                'type' => $t, 'on_file' => (bool) ($c->documents->firstWhere('type', $t)?->path),
            ])->values(),
        ]);

        return ApiResponse::collection($children);
    }
}
