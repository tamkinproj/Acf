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

        $query = Child::query()->with(['guardian', 'bankAccount', 'sponsorship'])->search($request->query('q'))->orderBy('ref');

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

    public function show(Child $child)
    {
        return ApiResponse::ok($child->toProfileApi());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true));
        $user = $request->user();

        $child = DB::transaction(function () use ($data, $user) {
            $guardian = null;
            if (! empty($data['guardian']['name'])) {
                $guardian = Guardian::create(['foundation_id' => $user->foundation_id] + $data['guardian']);
            }

            $child = Child::create([
                'foundation_id' => $user->foundation_id,
                'ref' => 'TMP-'.Str::random(12),
                'guardian_id' => $guardian?->id,
            ] + collect($data)->except(['guardian', 'bank', 'pay_type', 'monthly_amount', 'channel', 'sponsor_id'])->all());
            $child->update(['ref' => 'ACF-'.str_pad((string) $child->id, 4, '0', STR_PAD_LEFT)]);

            $channel = $data['channel'] ?? 'direct';
            $sponsorId = $channel === 'partner' && ! empty($data['sponsor_id'])
                ? Sponsor::where('id', $data['sponsor_id'])->value('id')   // scoped: only this foundation's sponsors
                : null;

            Sponsorship::create([
                'foundation_id' => $user->foundation_id, 'child_id' => $child->id, 'sponsor_id' => $sponsorId,
                'channel' => $channel, 'status' => 'pending', 'pay_type' => $data['pay_type'] ?? 'bank',
                'monthly_amount' => $data['monthly_amount'] ?? 0, 'start_date' => now()->toDateString(),
            ]);
            ChannelHistory::create(['foundation_id' => $user->foundation_id, 'child_id' => $child->id, 'channel' => $channel, 'since' => now()->toDateString()]);

            if (($data['pay_type'] ?? 'bank') === 'bank' && ! empty($data['bank'])) {
                BankAccount::create(['foundation_id' => $user->foundation_id, 'child_id' => $child->id] + $data['bank']);
            }

            return $child;
        });

        Audit::record('child.created', $request, 'child', $child->id);

        return ApiResponse::created($child->fresh()->toProfileApi());
    }

    public function update(Request $request, Child $child)
    {
        $data = $request->validate($this->rules(false));

        DB::transaction(function () use ($data, $child, $request) {
            if (! empty($data['guardian'])) {
                $guardian = $child->guardian ?? new Guardian(['foundation_id' => $request->user()->foundation_id]);
                $guardian->fill($data['guardian'])->save();
                $child->guardian_id = $guardian->id;
            }
            $child->fill(collect($data)->except(['guardian', 'bank', 'pay_type', 'monthly_amount', 'channel', 'sponsor_id'])->all())->save();
        });

        Audit::record('child.updated', $request, 'child', $child->id, ['fields' => array_keys($data)]);

        return ApiResponse::updated($child->fresh()->toProfileApi());
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name_en' => [$req, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'in:m,f'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:100'],
            'health_status' => ['nullable', 'in:good,needs_followup'],
            'grade' => ['nullable', 'string', 'max:40'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'mother_marital_status' => ['nullable', 'string', 'max:40'],
            'father_death_date' => ['nullable', 'date'],
            'brothers' => ['nullable', 'integer', 'min:0', 'max:30'],
            'sisters' => ['nullable', 'integer', 'min:0', 'max:30'],
            'status' => ['sometimes', 'in:active,pending,ended'],
            'guardian' => ['nullable', 'array'],
            'guardian.name' => ['required_with:guardian', 'string', 'max:150'],
            'guardian.relation' => ['nullable', 'string', 'max:60'],
            'guardian.phone' => ['nullable', 'string', 'max:40'],
            'pay_type' => $creating ? ['nullable', 'in:bank,cash'] : ['prohibited'],
            'monthly_amount' => $creating ? ['nullable', 'numeric', 'min:0', 'max:100000'] : ['prohibited'],
            'channel' => $creating ? ['nullable', 'in:direct,partner'] : ['prohibited'],
            'sponsor_id' => $creating ? ['nullable', 'integer'] : ['prohibited'],
            'bank' => $creating ? ['nullable', 'array'] : ['prohibited'],
            'bank.holder' => ['required_with:bank', 'string', 'max:150'],
            'bank.bank_name' => ['required_with:bank', 'string', 'max:100'],
            'bank.last4' => ['required_with:bank', 'regex:/^\d{4}$/'],
        ];
    }
}
