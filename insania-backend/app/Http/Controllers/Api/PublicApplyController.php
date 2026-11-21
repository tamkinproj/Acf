<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildApplication;
use App\Models\Foundation;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\OrphanFields;
use App\Support\PrivateImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The public application form (no sign-in). It only ever ADDS a line to the review queue: nothing here reads or changes an orphan record.
 * Guards: a hidden field only robots fill in, a per-address hourly limit (route) and a daily cap, strict checks on every answer,
 * photos decoded and saved again in a private folder, and nothing but the answers is stored (the address is kept only as a one-way hash).
 */
class PublicApplyController extends Controller
{
    public function status()
    {
        return ApiResponse::ok(['open' => (bool) config('features.public_apply') && Foundation::primary() !== null, 'max_photos' => (int) config('orphan_fields.photos.max_files'), 'max_documents' => (int) config('orphan_fields.documents.max_files')]);
    }

    public function store(Request $request)
    {
        if (! config('features.public_apply') || ! ($foundation = Foundation::primary())) {
            return ApiResponse::error('CLOSED', 'The application form is closed for now.', 503);
        }
        if ($request->filled('website')) {                       // the hidden field: a robot filled it in. Pretend it worked.
            return ApiResponse::created(['reference' => 'APP-00000']);
        }
        if (ChildApplication::withoutGlobalScopes()->where('created_at', '>=', now()->subDay())->count() >= (int) config('orphan_fields.apply.per_day')) {
            return ApiResponse::error('BUSY', 'Many applications were received today. Please try again tomorrow.', 429);
        }

        $maxFiles = (int) config('orphan_fields.photos.max_files');
        $rules = OrphanFields::rules(true) + [
            'photos' => ['nullable', 'array', 'max:'.$maxFiles],
            'photos.*' => ['file', 'max:'.(int) config('orphan_fields.photos.max_kb'), 'mimes:jpg,jpeg,png,webp'],
            'documents' => ['nullable', 'array', 'max:'.(int) config('orphan_fields.documents.max_files')],
            'documents.*' => ['file', 'max:'.(int) config('orphan_fields.documents.max_kb'), 'mimes:jpg,jpeg,png,webp'],
            'method' => ['nullable', 'in:form,documents'],
        ];
        $rules['birth_date'] = ['required', 'date', 'before:today', 'after:1990-01-01'];
        $rules['gender'] = ['required', 'in:m,f'];
        $rules['guardian_name'] = ['required', 'string', 'max:150'];
        $rules['guardian_phone'] = ['required', 'string', 'max:40', 'regex:/^[0-9+()\s-]{6,40}$/'];
        $rules['name_en'] = ['required_without:name_ar', 'nullable', 'string', 'max:150'];
        $rules['name_ar'] = ['required_without:name_en', 'nullable', 'string', 'max:150'];
        $data = $request->validate($rules);

        $answers = collect($data)->except(['photos', 'documents', 'method'])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $app = ChildApplication::withoutGlobalScopes()->create([
            'foundation_id' => $foundation->id, 'status' => 'new', 'data' => $answers, 'photos' => [], 'documents' => [], 'method' => $data['method'] ?? 'form',
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        $saved = ['photos' => [], 'documents' => []];
        foreach (['photos' => [1400, null], 'documents' => [2200, (int) config('orphan_fields.documents.max_kb')]] as $kind => [$side, $kb]) {
            foreach ((array) $request->file($kind, []) as $file) {
                $one = PrivateImages::store($file, "applications/{$app->id}/$kind", $side, $kb);
                if (is_string($one)) {
                    foreach (array_merge($saved['photos'], $saved['documents']) as $p) {
                        Storage::disk('local')->delete($p['path']);
                    }
                    $app->delete();

                    return ApiResponse::error('VALIDATION_ERROR', $one, 422, [$kind => [$one]]);
                }
                $saved[$kind][] = $one;
            }
        }
        $paths = $saved['photos'];
        $app->update($saved);
        Audit::record('application.received', $request, 'application', $app->id, ['photos' => count($paths), 'documents' => count($saved['documents']), 'method' => $app->method], null, $foundation->id);

        return ApiResponse::created(['reference' => $app->reference()]);
    }
}
