<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChildDocumentController extends Controller
{
    public function index(Child $child)
    {
        $docs = $child->documents()->get()->keyBy('type');

        return ApiResponse::collection(collect(Child::DOCUMENT_TYPES)->map(function ($t) use ($docs) {
            $doc = $docs->get($t);

            return [
                'type' => $t,
                'on_file' => (bool) $doc?->path,
                'original_name' => $doc?->original_name,
                'uploaded_at' => $doc?->path ? $doc->updated_at?->toIso8601String() : null,
            ];
        }));
    }

    public function store(Request $request, Child $child)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', Child::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->guessExtension() ?: 'bin');
        $path = $file->storeAs("children/{$child->foundation_id}/{$child->id}", Str::random(40).'.'.$ext, 'local');

        $existing = ChildDocument::where('child_id', $child->id)->where('type', $data['type'])->first();
        if ($existing?->path) {
            Storage::disk('local')->delete($existing->path);
        }

        $doc = ChildDocument::updateOrCreate(
            ['child_id' => $child->id, 'type' => $data['type']],
            ['foundation_id' => $child->foundation_id, 'path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 120, ''),
             'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id]
        );

        Audit::record('document.uploaded', $request, 'child', $child->id, ['type' => $data['type']]);

        return ApiResponse::created(['type' => $doc->type, 'on_file' => true]);
    }

    public function download(Request $request, Child $child, string $type)
    {
        $doc = ChildDocument::where('child_id', $child->id)->where('type', $type)->first();

        if (! $doc || ! $doc->path || ! Storage::disk('local')->exists($doc->path)) {
            return ApiResponse::notFound('No such document.');
        }

        Audit::record('document.downloaded', $request, 'child', $child->id, ['type' => $type]);

        return Storage::disk('local')->download($doc->path, $doc->type.'.'.pathinfo($doc->path, PATHINFO_EXTENSION), [
            'Content-Type' => $doc->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
