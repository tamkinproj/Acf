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
        $out = ['kind' => $user->toApi()['kind'], 'role' => $user->role, 'features_off' => PermissionCatalog::hasSwitchedOff($user)];

        if ($can('children.view')) {
            $banks = BankAccount::query();
            $out['children'] = [
                'total' => Child::count(),
                'bank_accounts' => (clone $banks)->count(),
                'bank_verified' => (clone $banks)->where('verified', true)->count(),
                'by_age' => (function () {
                    $b = ['0-4' => 0, '5-8' => 0, '9-12' => 0, '13-16' => 0, '17+' => 0];
                    foreach (Child::whereNotNull('birth_date')->pluck('birth_date') as $d) {
                        $a = \Illuminate\Support\Carbon::parse($d)->age;
                        $b[$a <= 4 ? '0-4' : ($a <= 8 ? '5-8' : ($a <= 12 ? '9-12' : ($a <= 16 ? '13-16' : '17+')))]++;
                    }

                    return $b;
                })(),
                'by_city' => Child::whereNotNull('city')->where('city', '!=', '')->selectRaw('city, count(*) as n')->groupBy('city')->orderByDesc('n')->limit(6)->pluck('n', 'city'),
                'samples' => Child::where('is_sample', true)->count(),
                'by_status' => ['active' => Child::where('status', 'active')->count(), 'pending' => Child::where('status', 'pending')->count(), 'ended' => Child::where('status', 'ended')->count()],
                'latest' => Child::orderByDesc('id')->limit(5)->get()->map(fn ($c) => ['id' => $c->id, 'ref' => $c->ref, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar, 'city' => $c->city, 'status' => $c->status, 'added' => $c->created_at?->toDateString()])->values(),
                'needs_attention' => Child::whereHas('bankAccount', fn ($b) => $b->where('verified', false))->orderBy('ref')->limit(6)->get()
                    ->map(fn ($c) => ['child_id' => $c->id, 'ref' => $c->ref, 'name_en' => $c->name_en, 'reason' => 'bank_not_verified'])->values(),
            ];
        }
        if ($can('applications.review')) {
            $out['applications'] = ['new' => \App\Models\ChildApplication::where('status', 'new')->count()];
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
                'by_role' => User::where('foundation_id', $user->foundation_id)->where('active', true)->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
                'latest_users' => User::where('foundation_id', $user->foundation_id)->orderByDesc('id')->limit(5)->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role, 'department' => $u->department_key, 'active' => (bool) $u->active, 'added' => $u->created_at?->toDateString()])->values(),
                'failed_logins_24h' => AuditLog::where('foundation_id', $user->foundation_id)->where('action', 'login.failed')->where('created_at', '>=', now()->subDay())->count(),
            ];
        }
        if ($can('family.view_own')) {
            $out['family'] = ['children' => ($user->guardian_id ? Child::where('guardian_id', $user->guardian_id)->count() : 0) + ($user->child_id ? 1 : 0)];
        }

        return ApiResponse::ok($out);
    }
}
