<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrphanReports\DarAlBerReport;
use App\Services\OrphanReports\ReportTemplates;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\Partners;
use Illuminate\Http\Request;

/**
 * The partner's report form (the PDF Dar Al Ber sends). The President adds it on the Partners page; the system only
 * fills its fields. Staff who work with orphans can see whether it is there.
 */
class ReportFormController extends Controller
{
    private function check(Request $request, string $code): ?\Illuminate\Http\JsonResponse
    {
        if (! Partners::byCode((int) $request->user()->foundation_id, $code)) {
            return ApiResponse::notFound('No such partner.');
        }
        if ($code !== DarAlBerReport::PARTNER) {
            return ApiResponse::error('NO_REPORT', 'The system fills only the Dar Al Ber report form for now.', 422);
        }

        return null;
    }

    private function out(Request $request, string $code): array
    {
        $info = ReportTemplates::info((int) $request->user()->foundation_id, $code);
        $v = $info['versions'][0] ?? null;

        return [
            'partner' => $code,
            'available' => (bool) $v,
            'current' => $v ? ['name' => $v['name'], 'size' => $v['size'], 'fields' => $v['fields'], 'uploaded_at' => $v['uploaded_at']] : null,
            'versions' => count($info['versions']),
            'org_name' => $info['org_name'],
            'country' => $info['country'],
        ];
    }

    public function show(Request $request, string $code)
    {
        return $this->check($request, $code) ?? ApiResponse::ok($this->out($request, $code));
    }

    public function upload(Request $request, string $code)
    {
        if ($e = $this->check($request, $code)) {
            return $e;
        }
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file');
        $bytes = (string) file_get_contents($file->getRealPath());
        if (! str_contains(substr($bytes, 0, 1024), '%PDF-')) {
            return ApiResponse::error('VALIDATION_ERROR', 'Choose the PDF form.', 422, ['file' => ['Choose the PDF form.']]);
        }
        $saved = ReportTemplates::store((int) $request->user()->foundation_id, $code, $bytes, (string) $file->getClientOriginalName(), (int) $request->user()->id);
        if (is_string($saved)) {
            return ApiResponse::error('VALIDATION_ERROR', $saved, 422, ['file' => [$saved]]);
        }
        Audit::record('report_form.uploaded', $request, null, null, ['partner' => $code, 'fields' => $saved['fields']]);

        return ApiResponse::updated($this->out($request, $code));
    }

    public function details(Request $request, string $code)
    {
        if ($e = $this->check($request, $code)) {
            return $e;
        }
        $data = $request->validate(['org_name' => ['required', 'string', 'max:150'], 'country' => ['required', 'string', 'regex:/^[A-Za-z]{2}$/']]);
        ReportTemplates::setDetails((int) $request->user()->foundation_id, $code, trim($data['org_name']), $data['country']);
        Audit::record('report_form.details', $request, null, null, ['partner' => $code]);

        return ApiResponse::updated($this->out($request, $code));
    }
}
