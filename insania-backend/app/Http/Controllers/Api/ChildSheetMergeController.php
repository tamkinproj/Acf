<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrphanSheetMerge;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\PartnerContext;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

/**
 * TEMPORARY (UPDATE-61): POST children/sheet-merge - adds the Google Form answer sheets to the orphans already saved,
 * matched by orphan number. apply=0 only reads the files and says what would happen; apply=1 saves.
 * It can add orphans (pending), so it needs both children.create and children.update.
 */
class ChildSheetMergeController extends Controller
{
    public function store(Request $request)
    {
        $held = PermissionCatalog::forUser($request->user());
        if (! in_array('children.create', $held, true) || ! in_array('children.update', $held, true)) {
            return ApiResponse::error('FORBIDDEN', 'This account is not allowed to do that.', 403);
        }
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:4'],
            'files.*' => ['file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'apply' => ['nullable', 'boolean'],
        ]);
        $apply = $request->boolean('apply');
        $files = array_map(fn ($f) => ['path' => $f->getRealPath(), 'name' => mb_substr((string) $f->getClientOriginalName(), 0, 120)], $request->file('files'));
        @set_time_limit(300);

        try {
            $report = OrphanSheetMerge::run($files, (int) $request->user()->foundation_id, PartnerContext::id(), $apply);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422, ['files' => [$e->getMessage()]]);
        }

        if ($apply) {   // counts only: no names or numbers in the log
            Audit::record('children.sheet_merged', $request, null, null, ['matched' => $report['matched'], 'updated' => $report['updated'], 'created' => $report['created'],
                'conflicts' => count($report['lists']['conflicts']), 'review' => count($report['lists']['unclear_numbers']) + count($report['lists']['birth_dates'])]);
        }

        return ApiResponse::ok($report);
    }
}
