<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Core\Documents\DocumentService;
use App\Core\Documents\DocumentStorage;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Program;
use App\Modules\Aytam\AytamAccess;
use App\Support\ApiResponse;
use App\Sync\RejectChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AytamDocumentController extends Controller
{
    public function __construct(private AytamAccess $access, private DocumentService $documents, private DocumentStorage $storage) {}

    public function index(Request $request, Program $program, string $aytam): JsonResponse
    {
        $record = $this->access->find($request->user(), $program, $aytam);
        $docs = Document::query()->where('aytam_id', $record->getKey())->orderBy('type')->orderByDesc('doc_version')->get();

        return ApiResponse::ok([
            'current' => $docs->where('is_current', true)->values()->map(fn ($d) => AytamController::presentDocument($d) + ['versions' => $docs->where('group_id', $d->group_id)->count()])->all(),
            'types' => Document::TYPES,
        ]);
    }

    /** Upload a document, or a new version of one (replaces=<current document id>). */
    public function store(Request $request, Program $program, string $aytam): JsonResponse
    {
        $record = $this->access->find($request->user(), $program, $aytam);
        $data = $request->validate([
            'file' => ['required', 'file'], 'type' => ['required', Rule::in(Document::TYPES)],
            'expires_on' => ['nullable', 'date', 'after:today'], 'notes' => ['nullable', 'string', 'max:2000'], 'replaces' => ['nullable', 'uuid'],
        ]);
        $replaces = isset($data['replaces']) ? Document::query()->where('aytam_id', $record->getKey())->where('is_current', true)->findOrFail($data['replaces']) : null;
        if ($replaces && $replaces->type !== $data['type']) {
            return ApiResponse::error('TYPE_MISMATCH', 'A new version must be the same kind of document.', 422);
        }

        try {
            $doc = $this->documents->upload($program, $request->file('file'), $data, $request->user(), $record, replaces: $replaces);
        } catch (RejectChange $e) {
            return ApiResponse::error('INVALID_FILE', $e->getMessage(), 422, ['file' => [$e->getMessage()]]);
        }

        return ApiResponse::created(AytamController::presentDocument($doc));
    }

    public function history(Request $request, Program $program, string $document): JsonResponse
    {
        $doc = $this->authorized($request, $program, $document);
        $versions = Document::query()->where('group_id', $doc->group_id)->orderByDesc('doc_version')->get();

        return ApiResponse::ok($versions->map(fn ($d) => AytamController::presentDocument($d))->all());
    }

    public function download(Request $request, Program $program, string $document)
    {
        $doc = $this->authorized($request, $program, $document);
        abort_unless($this->storage->exists($doc->storage_path), 404);

        return $this->storage->response($doc->storage_path, $doc->original_name, $doc->mime, inline: $request->boolean('inline'));
    }

    public function decide(Request $request, Program $program, string $document): JsonResponse
    {
        $doc = $this->authorized($request, $program, $document);
        $data = $request->validate([
            'decision' => ['required', Rule::in([Document::VERIFIED, Document::REJECTED])], 'reason' => ['nullable', 'string', 'max:500'], 'expires_on' => ['nullable', 'date'],
        ]);

        try {
            $this->documents->decide($doc, $data['decision'], $request->user(), $data['reason'] ?? null, $data['expires_on'] ?? null);
        } catch (RejectChange $e) {
            return ApiResponse::error(strtoupper($e->reason), $e->getMessage(), 422);
        }

        return ApiResponse::ok(AytamController::presentDocument($doc->refresh()));
    }

    /**
     * A document is reachable only through the record it belongs to (so a field worker cannot open an unassigned child's
     * papers), or - for a registration still waiting for review - by a reviewer.
     */
    private function authorized(Request $request, Program $program, string $id): Document
    {
        $doc = Document::query()->where('program_id', $program->getKey())->findOrFail($id);
        if ($doc->aytam_id !== null) {
            $this->access->find($request->user(), $program, $doc->aytam_id);
        } else {
            abort_unless($request->user()->hasPermission('aytam.review', $program), 404);
        }

        return $doc;
    }
}
