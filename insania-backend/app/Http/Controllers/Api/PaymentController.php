<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use App\Services\PaymentLedger;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentLedger $ledger)
    {
        $month = $request->query('month', now()->format('Y-m'));
        if (! PaymentLedger::validMonth($month)) {
            return ApiResponse::error('INVALID_MONTH', 'Use the format YYYY-MM.', 422);
        }

        $rows = $ledger->forMonth($month, $request->query('scope', 'all'));

        return ApiResponse::collection($rows, ['month' => $month] + $ledger->totals($rows));
    }

    public function markCash(Request $request, Child $child)
    {
        $data = $request->validate(['month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $sponsorship = Sponsorship::where('child_id', $child->id)->where('status', 'active')->first();
        if (! $sponsorship) {
            return ApiResponse::error('NO_SPONSORSHIP', 'This child has no active sponsorship.', 422);
        }
        if ($sponsorship->pay_type !== 'cash') {
            return ApiResponse::error('NOT_CASH', 'Only cash payments can be marked received here.', 422);
        }

        $payment = SponsorshipPayment::updateOrCreate(
            ['sponsorship_id' => $sponsorship->id, 'month' => $data['month']],
            ['foundation_id' => $sponsorship->foundation_id, 'amount' => $sponsorship->monthly_amount, 'method' => 'cash',
             'status' => 'paid', 'paid_at' => now(), 'recorded_by' => $request->user()->id]
        );

        Audit::record('payment.cash_received', $request, 'child', $child->id, ['month' => $data['month']]);

        return ApiResponse::updated(['child_id' => $child->id, 'month' => $payment->month, 'status' => $payment->status]);
    }
}
