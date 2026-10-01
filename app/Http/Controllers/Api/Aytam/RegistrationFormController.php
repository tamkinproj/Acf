<?php

namespace App\Http\Controllers\Api\Aytam;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RegistrationForm;
use App\Modules\Aytam\Registration\CanonicalFields;
use App\Modules\Aytam\Registration\FieldTypes;
use App\Modules\Aytam\Registration\FormBuilder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** The registration form builder: design, preview, publish, unpublish, and the public link. */
class RegistrationFormController extends Controller
{
    public function __construct(private FormBuilder $builder) {}

    /** What the builder offers: question types and the canonical fields a question can fill. */
    public function catalogue(): JsonResponse
    {
        return ApiResponse::ok([
            'types' => collect(FieldTypes::LABELS)->map(fn ($label, $key) => ['type' => $key, 'label' => $label, 'options' => FieldTypes::needsOptions($key), 'file' => FieldTypes::isFile($key)])->values(),
            'canonical' => collect(CanonicalFields::all())->map(fn ($def, $key) => ['key' => $key] + $def)->values(),
            'document_types' => \App\Models\Document::TYPES,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $forms = RegistrationForm::query()->where('program_id', $program->getKey())->withCount('registrations')->orderByDesc('updated_at')->get();

        return ApiResponse::ok($forms->map(fn (RegistrationForm $f) => $this->brief($f) + ['registrations_count' => $f->registrations_count])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $program = $request->attributes->get('program');
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'], 'template' => ['nullable', 'in:blank,standard']]);

        $form = new RegistrationForm;
        $form->forceFill(['program_id' => $program->getKey(), 'title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => RegistrationForm::DRAFT])->save();
        if (($data['template'] ?? 'standard') === 'standard') {
            $this->builder->applyStandardTemplate($form);
        }

        return ApiResponse::created($this->detail($form->refresh()));
    }

    public function show(Request $request, string $program, string $form): JsonResponse
    {
        return ApiResponse::ok($this->detail($this->find($request, $form)));
    }

    public function update(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'settings' => ['sometimes', 'array'], 'settings.success_message' => ['nullable', 'string', 'max:1000'], 'settings.closes_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        if (isset($data['settings'])) {
            $data['settings'] = array_filter(array_replace($record->settings ?? [], $data['settings']), fn ($v) => $v !== null && $v !== '');
        }
        $record->forceFill($data)->save();

        return ApiResponse::ok($this->detail($record->refresh()));
    }

    /** Replace the sections and questions. Editing a published form changes the DRAFT; the live link keeps serving the published version. */
    public function structure(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        $payload = Validator::make($request->all(), [
            'sections' => ['required', 'array', 'max:30'], 'sections.*.id' => ['nullable', 'uuid'], 'sections.*.title' => ['required', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'], 'sections.*.fields' => ['nullable', 'array', 'max:100'],
            'sections.*.fields.*.id' => ['nullable', 'uuid'], 'sections.*.fields.*.key' => ['nullable', 'string', 'max:60'],
            'sections.*.fields.*.label' => ['required', 'string', 'max:255'], 'sections.*.fields.*.type' => ['required', 'string', 'max:30'],
            'sections.*.fields.*.required' => ['nullable', 'boolean'], 'sections.*.fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'sections.*.fields.*.options' => ['nullable', 'array'], 'sections.*.fields.*.maps_to' => ['nullable', 'string', 'max:60'],
            'sections.*.fields.*.document_type' => ['nullable', 'string', 'max:40'],
        ])->validate();

        // New questions get a key from their label; the builder never has to invent one.
        $seen = [];
        foreach ($payload['sections'] as $i => $s) {
            foreach ($s['fields'] ?? [] as $j => $f) {
                $key = $f['key'] ?? null ?: Str::slug($f['label'], '_');
                $key = preg_match('/^[a-z]/', $key) ? $key : 'q_'.$key;
                $base = $key = mb_substr($key ?: 'question', 0, 50);
                for ($n = 2; isset($seen[$key]); $n++) {
                    $key = $base.'_'.$n;
                }
                $seen[$key] = true;
                $payload['sections'][$i]['fields'][$j]['key'] = $key;
            }
        }
        $this->builder->replace($record, $payload);

        return ApiResponse::ok($this->detail($record->refresh()));
    }

    /** What would stop this form being published right now. */
    public function check(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);

        return ApiResponse::ok(['problems' => $this->builder->problems($record), 'structure' => $this->builder->structure($record)]);
    }

    public function publish(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        $this->builder->publish($record, $request->user());

        return ApiResponse::ok($this->detail($record->refresh()));
    }

    public function unpublish(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        if ($record->status !== RegistrationForm::PUBLISHED) {
            return ApiResponse::error('NOT_PUBLISHED', 'This form is not published.', 409);
        }
        $record->forceFill(['status' => RegistrationForm::UNPUBLISHED])->save();

        return ApiResponse::ok($this->detail($record->refresh()));
    }

    /** Invalidates the old link (e.g. it was shared somewhere it should not have been) and creates a new one. */
    public function regenerateLink(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        $record->forceFill(['public_token' => Str::lower(Str::random(40))])->save();
        app(\App\Core\Audit\Auditor::class)->record('form.link_regenerated', "Registration link of \"{$record->title}\" was replaced", 'registration_forms', $record->getKey());

        return ApiResponse::ok($this->detail($record->refresh()));
    }

    public function destroy(Request $request, string $program, string $form): JsonResponse
    {
        $record = $this->find($request, $form);
        if ($record->registrations()->exists()) {
            return ApiResponse::error('IN_USE', 'This form has registrations. Unpublish it instead.', 409);
        }
        $record->delete();

        return ApiResponse::ok(null);
    }

    private function find(Request $request, string $id): RegistrationForm
    {
        /** @var Program $program */
        $program = $request->attributes->get('program');

        return RegistrationForm::query()->where('program_id', $program->getKey())->findOrFail($id);
    }

    private function brief(RegistrationForm $f): array
    {
        return [
            'id' => $f->getKey(), 'title' => $f->title, 'status' => $f->status, 'published_version' => $f->published_version,
            'link' => $f->status === RegistrationForm::PUBLISHED && $f->public_token ? url('/apply/'.$f->public_token) : null,
            'closes_on' => $f->settings['closes_on'] ?? null, 'updated_at' => $f->updated_at?->toIso8601String(),
        ];
    }

    private function detail(RegistrationForm $f): array
    {
        $latest = $f->versions()->first();

        return $this->brief($f) + [
            'description' => $f->description, 'settings' => $f->settings ?? (object) [], 'link_token_exists' => $f->public_token !== null,
            'structure' => $this->builder->structure($f),
            'unpublished_changes' => $latest ? $f->updated_at?->gt($latest->published_at) : true,
            'problems' => $this->builder->problems($f),
        ];
    }
}
