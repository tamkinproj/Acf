<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;

class BankVerificationController extends Controller
{
    public function update(Request $request, Child $child)
    {
        $data = $request->validate(['verified' => ['required', 'boolean']]);

        $bank = $child->bankAccount;
        if (! $bank) {
            return ApiResponse::error('NO_BANK_ACCOUNT', 'This child has no bank account on file.', 422);
        }

        $bank->update([
            'verified' => $data['verified'],
            'verified_by' => $data['verified'] ? $request->user()->id : null,
            'verified_at' => $data['verified'] ? now() : null,
        ]);

        Audit::record($data['verified'] ? 'bank.verified' : 'bank.unverified', $request, 'child', $child->id);

        return ApiResponse::updated(['verified' => $bank->verified]);
    }
}
