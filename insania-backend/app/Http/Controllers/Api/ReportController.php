<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentLedger;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary(Request $request, PaymentLedger $ledger)
    {
        [$month, $scope] = $this->params($request);
        if (! PaymentLedger::validMonth($month)) {
            return ApiResponse::error('INVALID_MONTH', 'Use the format YYYY-MM.', 422);
        }

        return ApiResponse::ok(['month' => $month, 'scope' => $scope] + $ledger->totals($ledger->forMonth($month, $scope)));
    }

    public function paymentsCsv(Request $request, PaymentLedger $ledger)
    {
        [$month, $scope] = $this->params($request);
        if (! PaymentLedger::validMonth($month)) {
            return ApiResponse::error('INVALID_MONTH', 'Use the format YYYY-MM.', 422);
        }

        $rows = [['ref', 'name_en', 'method', 'status', 'amount']];
        foreach ($ledger->forMonth($month, $scope) as $r) {
            $rows[] = [$r['ref'], $r['name_en'], $r['method'], $r['status'], $r['amount']];
        }

        Audit::record('report.exported', $request, null, null, ['report' => 'payments', 'month' => $month, 'scope' => $scope]);

        return response(Csv::build($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="payments-'.$month.'.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function params(Request $request): array
    {
        $scope = $request->query('scope', 'all');

        return [(string) $request->query('month', now()->format('Y-m')), in_array($scope, ['all', 'bank', 'cash'], true) ? $scope : 'all'];
    }
}
