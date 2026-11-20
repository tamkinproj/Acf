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

        if (! $user->guardian_id) {
            return ApiResponse::collection([]);
        }

        $children = Child::query()->with('documents')->where('guardian_id', $user->guardian_id)->orderBy('ref')->get()->map(fn (Child $c) => [
            'id' => $c->id, 'ref' => $c->ref, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar,
            'grade' => $c->grade, 'status' => $c->status,
            'documents' => collect(Child::DOCUMENT_TYPES)->map(fn ($t) => [
                'type' => $t, 'on_file' => (bool) ($c->documents->firstWhere('type', $t)?->path),
            ])->values(),
        ]);

        return ApiResponse::collection($children);
    }
}
