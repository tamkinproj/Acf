<?php

namespace App\Services;

use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use Illuminate\Support\Collection;

/**
 * One place that answers "who is paid for a given month". The payments screen, the reports and the partner summary
 * all read from here, so they can never disagree.
 */
class PaymentLedger
{
    public static function validMonth(?string $month): bool
    {
        return is_string($month) && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
    }

    /**
     * @param  string  $scope  all | bank | cash
     * @param  int|null  $foundationId  set only when nobody is signed in (partner API key); otherwise the signed-in scope applies
     * @return Collection<int, array<string, mixed>>
     */
    public function forMonth(string $month, string $scope = 'all', ?int $foundationId = null): Collection
    {
        $sponsorships = Sponsorship::query()->with('child.guardian')->where('status', 'active');
        $payments = SponsorshipPayment::query()->where('month', $month);

        if ($foundationId !== null) {
            $sponsorships->withoutGlobalScopes()->where('foundation_id', $foundationId);
            $payments->withoutGlobalScopes()->where('foundation_id', $foundationId);
        }
        if (in_array($scope, ['bank', 'cash'], true)) {
            $sponsorships->where('pay_type', $scope);
        }

        $paid = $payments->get()->keyBy('sponsorship_id');

        return $sponsorships->get()
            ->filter(fn ($s) => $s->child !== null)
            ->map(function ($s) use ($paid) {
                $p = $paid->get($s->id);

                return [
                    'child_id' => $s->child_id,
                    'ref' => $s->child->ref,
                    'name_en' => $s->child->name_en,
                    'name_ar' => $s->child->name_ar,
                    'method' => $s->pay_type,
                    'amount' => (float) ($p?->amount ?? $s->monthly_amount),
                    'status' => $p?->status ?? 'pending',
                    'paid_at' => $p?->paid_at?->toIso8601String(),
                ];
            })
            ->sortBy('ref')
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function totals(Collection $rows): array
    {
        $paid = $rows->where('status', 'paid');
        $due = $rows->where('status', '!=', 'paid');

        return [
            'children' => $rows->count(),
            'paid_count' => $paid->count(),
            'pending_count' => $due->count(),
            'paid_total' => round((float) $paid->sum('amount'), 2),
            'due_total' => round((float) $due->sum('amount'), 2),
        ];
    }
}
