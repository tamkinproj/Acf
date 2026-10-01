<?php

namespace App\Http\Controllers;

use App\Models\Foundation;
use App\Models\Program;
use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormVersion;
use App\Modules\Aytam\Registration\FieldTypes;
use App\Modules\Aytam\Registration\RegistrationService;
use App\Modules\Aytam\Registration\SubmissionValidator;
use App\Sync\RejectChange;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

/**
 * What an applicant sees: a registration form reached by a link, a confirmation, and a private status page where a
 * returned registration can be corrected. Rendered on the server as plain HTML, so it works on any phone with no app,
 * no sign-in and no JavaScript.
 */
class PublicRegistrationController extends Controller
{
    public function __construct(private TenantContext $tenant, private SubmissionValidator $validator, private RegistrationService $service) {}

    public function show(string $token): View|Response
    {
        [$form, $version, $foundation, $open] = $this->resolveForm($token);
        if (! $open) {
            return $this->closed($foundation, 403);
        }

        return view('public.form', $this->formData($foundation, $form, $version) + ['action' => url('/apply/'.$token), 'values' => [], 'files' => [], 'correction' => null]);
    }

    public function submit(Request $request, string $token): RedirectResponse|Response
    {
        [$form, $version, $foundation, $open] = $this->resolveForm($token);
        if (! $open) {
            return $this->closed($foundation, 403);
        }
        // A hidden field real people never see: a filled one is a bot. Pretend it worked so it learns nothing.
        if ($request->filled('website')) {
            return redirect('/apply/'.$token.'/done');
        }

        $input = $this->validator->validate($version->schema, $request);
        $result = $this->service->submit($form, $version, $input['answers'], $input['files'], $request->ip());

        return redirect('/apply/'.$token.'/done')->with('registration', [
            'reference' => $result['registration']->reference, 'status_url' => url('/apply/status/'.$result['access_token']),
        ]);
    }

    public function done(Request $request, string $token): View|RedirectResponse
    {
        [$form, , $foundation, $open] = $this->resolveForm($token);
        $info = $request->session()->get('registration');
        if (! $info) {
            return redirect('/apply/'.$token);
        }

        return view('public.done', ['foundation' => $foundation, 'form' => $form, 'info' => $info, 'message' => $form->settings['success_message'] ?? null]);
    }

    public function status(string $accessToken): View
    {
        [$reg, $foundation] = $this->resolveRegistration($accessToken);
        $schema = $reg->formVersion->schema;
        $base = ['foundation' => $foundation, 'registration' => $reg, 'status_url' => url('/apply/status/'.$accessToken)];

        if ($reg->status !== Registration::NEEDS_CORRECTION) {
            return view('public.status', $base);
        }

        return view('public.form', $this->formData($foundation, $reg->form, $reg->formVersion) + [
            'action' => $base['status_url'], 'values' => $reg->answers, 'correction' => $reg,
            'files' => collect($reg->answers)->filter(fn ($v) => is_array($v) && isset($v['document_id']))->map(fn ($v) => $v['name'])->all(),
        ]);
    }

    public function resubmit(Request $request, string $accessToken): RedirectResponse
    {
        [$reg] = $this->resolveRegistration($accessToken);
        $schema = $reg->formVersion->schema;
        $has = collect($reg->answers)->filter(fn ($v) => is_array($v) && isset($v['document_id']))->map(fn () => true)->all();

        $input = $this->validator->validate($schema, $request, $has);
        try {
            $this->service->resubmit($reg, $schema, $input['answers'], $input['files']);
        } catch (RejectChange $e) {
            return redirect('/apply/status/'.$accessToken);
        }

        return redirect('/apply/status/'.$accessToken)->with('resubmitted', true);
    }

    // ---- resolving links ----

    /** @return array{0:RegistrationForm,1:RegistrationFormVersion,2:Foundation,3:bool} */
    private function resolveForm(string $token): array
    {
        $form = $this->tenant->asSystem(fn () => RegistrationForm::query()->where('public_token', $token)->first());
        abort_unless($form, 404);
        $this->tenant->setTenant($form->foundation_id);

        $foundation = Foundation::query()->findOrFail($form->foundation_id);
        $program = Program::query()->find($form->program_id);
        $version = RegistrationFormVersion::query()->where('form_id', $form->getKey())->where('version', max(1, $form->published_version))->first();
        $open = $foundation->isActive() && $program?->status === Program::ACTIVE && $form->isOpen() && $version !== null;

        return [$form, $version, $foundation, $open];
    }

    /** @return array{0:Registration,1:Foundation} */
    private function resolveRegistration(string $accessToken): array
    {
        $reg = $this->tenant->asSystem(fn () => Registration::query()->where('access_token_hash', hash('sha256', $accessToken))->first());
        abort_unless($reg, 404);
        $this->tenant->setTenant($reg->foundation_id);
        $foundation = Foundation::query()->findOrFail($reg->foundation_id);
        abort_unless($foundation->isActive(), 404);

        return [$reg->load(['formVersion', 'form']), $foundation];
    }

    private function formData(Foundation $foundation, RegistrationForm $form, RegistrationFormVersion $version): array
    {
        return ['foundation' => $foundation, 'form' => $form, 'schema' => $version->schema, 'maxMb' => (int) (config('foundation.uploads.document_max_kb') / 1024), 'isFile' => [FieldTypes::class, 'isFile']];
    }

    private function closed(Foundation $foundation, int $status): Response
    {
        return response()->view('public.closed', ['foundation' => $foundation], $status);
    }
}
