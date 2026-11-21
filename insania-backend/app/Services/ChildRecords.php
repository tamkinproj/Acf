<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChannelHistory;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Support\OrphanFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates an orphan record with its guardian, bank account and first sponsorship line. Used by "Add orphan" and by "approve an application". */
final class ChildRecords
{
    /**
     * @param  array  $data  child columns (flat keys of config/orphan_fields.php), optional nested guardian[name,relation,phone] and bank[holder,bank_name,last4],
     *                       pay_type, monthly_amount, channel, sponsor_id, status
     */
    public static function create(array $data, int $foundationId): Child
    {
        return DB::transaction(function () use ($data, $foundationId) {
            $guardian = null;
            if (! empty($data['guardian']['name'])) {
                $guardian = Guardian::withoutGlobalScopes()->create(['foundation_id' => $foundationId] + $data['guardian']);
            }

            $columns = collect($data)->only(OrphanFields::childKeys())->all();
            $child = Child::withoutGlobalScopes()->create([
                'foundation_id' => $foundationId,
                'ref' => 'TMP-'.Str::random(12),
                'guardian_id' => $guardian?->id,
            ] + $columns + array_filter(['status' => $data['status'] ?? null, 'form_submitted_at' => $data['form_submitted_at'] ?? null]));
            $child->update(['ref' => 'ACF-'.str_pad((string) $child->id, 4, '0', STR_PAD_LEFT)]);

            $channel = $data['channel'] ?? 'direct';
            $sponsorId = $channel === 'partner' && ! empty($data['sponsor_id'])
                ? Sponsor::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('id', $data['sponsor_id'])->value('id')
                : null;

            Sponsorship::withoutGlobalScopes()->create([
                'foundation_id' => $foundationId, 'child_id' => $child->id, 'sponsor_id' => $sponsorId,
                'channel' => $channel, 'status' => 'pending', 'pay_type' => $data['pay_type'] ?? 'bank',
                'monthly_amount' => $data['monthly_amount'] ?? 0, 'start_date' => now()->toDateString(),
            ]);
            ChannelHistory::withoutGlobalScopes()->create(['foundation_id' => $foundationId, 'child_id' => $child->id, 'channel' => $channel, 'since' => now()->toDateString()]);

            if (($data['pay_type'] ?? 'bank') === 'bank' && ! empty($data['bank'])) {
                BankAccount::withoutGlobalScopes()->create(['foundation_id' => $foundationId, 'child_id' => $child->id] + $data['bank']);
            }

            return $child;
        });
    }

    /** Turns the flat answers of the public form into the shape create() expects. */
    public static function fromFlat(array $flat): array
    {
        $out = collect($flat)->only(OrphanFields::childKeys())->all();
        $g = array_filter(['name' => $flat['guardian_name'] ?? null, 'relation' => $flat['guardian_relation'] ?? null, 'phone' => $flat['guardian_phone'] ?? null], fn ($v) => $v !== null && $v !== '');
        if ($g) {
            $out['guardian'] = $g + ['name' => $g['name'] ?? ($flat['guardian_relation'] ?? '-')];
        }
        $b = ['holder' => $flat['bank_holder'] ?? null, 'bank_name' => $flat['bank_name'] ?? null, 'last4' => $flat['bank_last4'] ?? null];
        $out['pay_type'] = count(array_filter($b)) === 3 ? 'bank' : 'cash';
        if ($out['pay_type'] === 'bank') {
            $out['bank'] = $b;
        }

        return $out;
    }
}
