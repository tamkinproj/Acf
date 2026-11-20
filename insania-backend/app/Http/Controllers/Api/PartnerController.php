<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentLedger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * A partner's read-only report, called with an API key (not a login). Totals only: no names, references or documents.
 * Uses the ApiKeyAuth middleware carried over from the old system.
 */
class PartnerController extends Controller
{
    public function summary(Request $request, PaymentLedger $ledger)
    {
        $key = $request->attributes->get('apiKey');

        if (! $key->foundation_id || ! in_array('partner.summary', $key->abilities ?? [], true)) {
            return ApiResponse::forbidden('This key cannot read the partner summary.');
        }

        $month = $request->query('month', now()->format('Y-m'));
        if (! PaymentLedger::validMonth($month)) {
            return ApiResponse::error('INVALID_MONTH', 'Use the format YYYY-MM.', 422);
        }

        $rows = $ledger->forMonth($month, 'all', (int) $key->foundation_id);

        return ApiResponse::ok(['month' => $month, 'sponsored_children' => $rows->count()] + collect($ledger->totals($rows))->except('children')->all());
    }
}
