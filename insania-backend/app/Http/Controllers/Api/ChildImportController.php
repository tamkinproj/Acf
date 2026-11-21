<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrphanSheetImport;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;

class ChildImportController extends Controller
{
    /** POST children/import - dry_run=1 only reads the file and says what would happen; without it the orphans are saved. */
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
            'pay_type' => ['nullable', 'in:auto,cash,bank'],
            'dry_run' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('file');
        $dry = $request->boolean('dry_run');

        try {
            $report = OrphanSheetImport::run($file->getRealPath(), $file->getClientOriginalName(), (string) $request->input('pay_type', 'auto'), $request->user()->foundation_id, $dry);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('VALIDATION_ERROR', $e->getMessage(), 422, ['file' => [$e->getMessage()]]);
        }

        if (! $dry) {
            Audit::record('children.imported', $request, null, null, ['new' => $report['new'], 'updated' => $report['updated'], 'skipped' => $report['skipped']]);
        }

        return ApiResponse::ok($report);
    }
}
