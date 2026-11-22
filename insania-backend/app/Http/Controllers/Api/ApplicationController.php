<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildApplication;
use App\Models\ChildPhoto;
use App\Services\ChildRecords;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\OrphanFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** The Aytam staff's side of the public form: read what came in, approve it into an orphan record, or reject it. */
class ApplicationController extends Controller
{
    private function row(ChildApplication $a): array
    {
        $d = $a->data ?? [];

        return [
            'id' => $a->id, 'reference' => $a->reference(), 'status' => $a->status,
            'name_en' => $d['name_en'] ?? null, 'name_ar' => $d['name_ar'] ?? null,
            'guardian' => $d['guardian_name'] ?? null, 'phone' => $d['guardian_phone'] ?? null, 'city' => $d['city'] ?? null,
            'birth_date' => $d['birth_date'] ?? null, 'photos' => count($a->photos ?? []), 'documents' => count($a->documents ?? []), 'document_types' => array_map(fn ($d) => $d['type'] ?? 'other', array_values($a->documents ?? [])), 'method' => $a->method ?? 'form',
            'submitted_at' => $a->created_at?->toIso8601String(), 'child_id' => $a->child_id,
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:new,approved,rejected']]);
        $q = ChildApplication::query()->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        $counts = ChildApplication::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return ApiResponse::ok([
            'items' => $q->limit(200)->get()->map(fn ($a) => $this->row($a))->values(),
            'counts' => ['new' => (int) ($counts['new'] ?? 0), 'approved' => (int) ($counts['approved'] ?? 0), 'rejected' => (int) ($counts['rejected'] ?? 0)],
        ]);
    }

    public function show(ChildApplication $application)
    {
        $d = collect(OrphanFields::all())->map(fn ($def, $k) => $application->data[$k] ?? null)->all();

        return ApiResponse::ok($this->row($application) + ['details' => $d, 'note' => $application->note]);
    }

    public function photo(ChildApplication $application, int $n)
    {
        $p = ($application->photos ?? [])[$n] ?? null;

        return $this->file($p['path'] ?? null, $p['mime'] ?? 'image/jpeg');
    }

    public function document(ChildApplication $application, int $n)
    {
        $p = ($application->documents ?? [])[$n] ?? null;

        return $this->file($p['path'] ?? null, $p['mime'] ?? 'image/jpeg');
    }

    public function approve(Request $request, ChildApplication $application)
    {
        if ($application->status !== 'new') {
            return ApiResponse::error('ALREADY_REVIEWED', 'This application was already reviewed.', 422);
        }
        $user = $request->user();

        $child = DB::transaction(function () use ($application, $user) {
            $flat = $application->data + ['status' => 'active'];
            $child = ChildRecords::create(ChildRecords::fromFlat($flat) + ['status' => 'active', 'form_submitted_at' => $application->created_at], $user->foundation_id);
            foreach ($application->photos ?? [] as $i => $p) {                       // the photos move to the child's own folder
                $to = "children/{$child->foundation_id}/{$child->id}/photo-".($i + 1).'-'.bin2hex(random_bytes(6)).'.jpg';
                if (Storage::disk('local')->exists($p['path']) && Storage::disk('local')->copy($p['path'], $to)) {
                    ChildPhoto::create(['foundation_id' => $child->foundation_id, 'child_id' => $child->id, 'path' => $to, 'mime' => $p['mime'] ?? 'image/jpeg']);
                }
            }
            foreach ($application->documents ?? [] as $d) {                           // a paper sent under its own name (birth certificate...) goes to the profile
                $type = $d['type'] ?? null;
                if (! in_array($type, Child::DOCUMENT_TYPES, true) || ! Storage::disk('local')->exists($d['path'] ?? '')) {
                    continue;
                }
                $to = "children/{$child->foundation_id}/{$child->id}/".bin2hex(random_bytes(20)).'.jpg';
                if (Storage::disk('local')->copy($d['path'], $to)) {
                    \App\Models\ChildDocument::updateOrCreate(['child_id' => $child->id, 'type' => $type], ['foundation_id' => $child->foundation_id, 'path' => $to,
                        'original_name' => $type.'.jpg', 'mime' => $d['mime'] ?? 'image/jpeg', 'size' => Storage::disk('local')->size($to), 'uploaded_by' => $user->id]);
                }
            }
            $application->update(['status' => 'approved', 'child_id' => $child->id, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);

            return $child;
        });

        Audit::record('application.approved', $request, 'application', $application->id, ['child_id' => $child->id]);

        return ApiResponse::ok($this->row($application->fresh()) + ['child' => $child->fresh()->toListApi()]);
    }

    public function reject(Request $request, ChildApplication $application)
    {
        if ($application->status !== 'new') {
            return ApiResponse::error('ALREADY_REVIEWED', 'This application was already reviewed.', 422);
        }
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $application->update(['status' => 'rejected', 'note' => $data['note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        Audit::record('application.rejected', $request, 'application', $application->id);

        return ApiResponse::ok($this->row($application->fresh()));
    }

    /** Streams a private photo to a signed-in reviewer only. */
    public static function file(?string $path, string $mime)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return ApiResponse::notFound('No such photo.');
        }

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
