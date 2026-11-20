<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Child;
use App\Models\Project;
use App\Models\User;
use App\Services\PaymentLedger;
use App\Support\ApiResponse;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

/** One endpoint, shaped by who is asking: each role only ever receives the parts it is allowed to see. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PaymentLedger $ledger)
    {
        $user = $request->user();
        $can = fn (string $p) => PermissionCatalog::allows($user, $p);
        $out = ['kind' => $user->toApi()['kind'], 'role' => $user->role];

        if ($can('children.view')) {
            $banks = BankAccount::query();
            $out['children'] = [
                'total' => Child::count(),
                'bank_accounts' => (clone $banks)->count(),
                'bank_verified' => (clone $banks)->where('verified', true)->count(),
                'needs_attention' => Child::whereHas('bankAccount', fn ($b) => $b->where('verified', false))->orderBy('ref')->limit(6)->get()
                    ->map(fn ($c) => ['child_id' => $c->id, 'ref' => $c->ref, 'name_en' => $c->name_en, 'reason' => 'bank_not_verified'])->values(),
            ];
        }
        if ($can('payments.view')) {
            $month = now()->format('Y-m');
            $out['payments'] = ['month' => $month] + $ledger->totals($ledger->forMonth($month));
        }
        if ($can('projects.view')) {
            $out['projects'] = ['active' => Project::where('status', 'active')->count(), 'total' => Project::count()];
        }
        if ($can('system.health')) {
            $out['system'] = ['php' => PHP_VERSION, 'laravel' => app()->version(), 'environment' => app()->environment()];
        }
        if ($can('users.manage')) {
            $out['security'] = [
                'active_users' => User::where('foundation_id', $user->foundation_id)->where('active', true)->count(),
                'inactive_users' => User::where('foundation_id', $user->foundation_id)->where('active', false)->count(),
                'failed_logins_24h' => AuditLog::where('foundation_id', $user->foundation_id)->where('action', 'login.failed')->where('created_at', '>=', now()->subDay())->count(),
            ];
        }
        if ($can('family.view_own')) {
            $out['family'] = ['children' => $user->guardian_id ? Child::where('guardian_id', $user->guardian_id)->count() : 0];
        }

        return ApiResponse::ok($out);
    }
}
