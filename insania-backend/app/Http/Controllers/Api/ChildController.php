<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ChannelHistory;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\OrphanFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChildController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'pay_type' => ['nullable', 'in:bank,cash'],
            'status' => ['nullable', 'in:active,pending,ended'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Child::query()->with(['guardian', 'bankAccount', 'sponsorship', 'account:id,child_id,username', 'photos:id,child_id'])->search($request->query('q'))->orderBy('ref');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('pay_type')) {
            $query->whereHas('sponsorship', fn ($s) => $s->where('pay_type', $request->query('pay_type')));
        }

        $page = $query->paginate((int) $request->query('per_page', 25));
        $page->setCollection($page->getCollection()->map(fn (Child $c) => $c->toListApi()));

        return ApiResponse::collection($page);
    }

    /** One photo of a child, for staff who may see the child. */
    public function photo(Child $child, int $photo)
    {
        $p = $child->photos()->where('id', $photo)->first();

        return ApplicationController::file($p?->path, $p?->mime ?? 'image/jpeg');
    }

    /** Staff add a photo of the child (private, re-saved, shrunk). */
    public function addPhoto(Request $request, Child $child)
    {
        $request->validate(['photo' => ['required', 'file', 'max:'.(int) config('orphan_fields.photos.max_kb'), 'mimes:jpg,jpeg,png,webp']]);
        if ($child->photos()->count() >= (int) config('orphan_fields.photos.max_per_child', 8)) {
            return ApiResponse::error('TOO_MANY', 'This child already has the most photos allowed. Remove one first.', 422);
        }
        $saved = \App\Support\PrivateImages::store($request->file('photo'), "children/{$child->foundation_id}/{$child->id}");
        if (is_string($saved)) {
            return ApiResponse::error('VALIDATION_ERROR', $saved, 422, ['photo' => [$saved]]);
        }
        $photo = \App\Models\ChildPhoto::create(['foundation_id' => $child->foundation_id, 'child_id' => $child->id] + $saved);
        Audit::record('child.photo_added', $request, 'child', $child->id);

        return ApiResponse::created(['id' => $photo->id]);
    }

    public function removePhoto(Request $request, Child $child, int $photo)
    {
        $p = $child->photos()->where('id', $photo)->first();
        if (! $p) {
            return ApiResponse::notFound('No such photo.');
        }
        \Illuminate\Support\Facades\Storage::disk('local')->delete($p->path);
        $p->delete();
        Audit::record('child.photo_removed', $request, 'child', $child->id);

        return ApiResponse::deleted('Photo removed.');
    }

    /** Removes the sample orphans that came with the system (and only those). */
    public function removeSamples(Request $request)
    {
        $ids = Child::where('is_sample', true)->pluck('id');
        $guardianIds = Child::where('is_sample', true)->whereNotNull('guardian_id')->pluck('guardian_id');
        DB::transaction(function () use ($ids, $guardianIds) {
            Child::whereIn('id', $ids)->delete();
            Guardian::whereIn('id', $guardianIds)->whereDoesntHave('children')->delete();
        });
        Audit::record('child.samples_removed', $request, null, null, ['count' => $ids->count()]);

        return ApiResponse::ok(['removed' => $ids->count()]);
    }

    public function show(Child $child)
    {
        return ApiResponse::ok($child->toProfileApi());
    }

    public function store(Request $request)
    {
        $request->merge(\App\Support\NameParts::prepare($request->all()));
        $data = $request->validate($this->rules(true));
        $user = $request->user();

        $child = \App\Services\ChildRecords::create($data, $user->foundation_id);

        Audit::record('child.created', $request, 'child', $child->id);

        return ApiResponse::created($child->fresh()->toProfileApi());
    }

    public function update(Request $request, Child $child)
    {
        $request->merge(\App\Support\NameParts::prepare($request->all(), $child));
        $data = $request->validate($this->rules(false));

        DB::transaction(function () use ($data, $child, $request) {
            if (! empty($data['guardian'])) {
                $guardian = $child->guardian ?? new Guardian(['foundation_id' => $request->user()->foundation_id]);
                $guardian->fill($data['guardian'])->save();
                $child->guardian_id = $guardian->id;
            }
            $child->fill(collect($data)->except(['guardian', 'bank', 'pay_type', 'monthly_amount', 'channel', 'sponsor_id'])->all())->save();

            // The bank account can be corrected. A changed account is not verified any more: staff check it again.
            if (! empty($data['bank'])) {
                $bank = $child->bankAccount ?? new \App\Models\BankAccount(['foundation_id' => $child->foundation_id, 'child_id' => $child->id]);
                $bank->fill($data['bank']);
                if (! $bank->exists || $bank->isDirty(['holder', 'bank_name', 'last4'])) {
                    $bank->verified = false;
                    $bank->verified_by = null;
                    $bank->verified_at = null;
                }
                $bank->save();
            }
        });

        Audit::record('child.updated', $request, 'child', $child->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($child->fresh()->toProfileApi());
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        $flat = collect(OrphanFields::rules())->only(OrphanFields::childKeys())->all();
        unset($flat['name_en']);

        return $flat + [
            'name_en' => [$req, 'string', 'max:150'],
            'status' => ['sometimes', 'in:active,pending,ended'],
            'guardian' => ['nullable', 'array'],
            'guardian.name' => ['required_with:guardian', 'string', 'max:150'],
            'guardian.relation' => ['nullable', 'string', 'max:60'],
            'guardian.phone' => ['nullable', 'string', 'max:40'],
            'pay_type' => $creating ? ['nullable', 'in:bank,cash'] : ['prohibited'],
            'monthly_amount' => $creating ? ['nullable', 'numeric', 'min:0', 'max:100000'] : ['prohibited'],
            'channel' => $creating ? ['nullable', 'in:direct,partner'] : ['prohibited'],
            'sponsor_id' => $creating ? ['nullable', 'integer'] : ['prohibited'],
            'bank' => ['nullable', 'array'],
            'bank.holder' => ['required_with:bank', 'string', 'max:150'],
            'bank.bank_name' => ['required_with:bank', 'string', 'max:100'],
            'bank.last4' => ['required_with:bank', 'regex:/^\d{4}$/'],
        ];
    }
}
