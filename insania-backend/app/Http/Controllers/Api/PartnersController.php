<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\Partners;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The partner list. Every staff member can see it; only the President Admin can pause or resume a partner.
 * There is no delete and no unlink: pausing stops the work, and the records stay.
 */
class PartnersController extends Controller
{
    /** The President sees every partner (working, paused, not set up). Other staff see only the partners that are working. */
    private function list(Request $request): array
    {
        $all = in_array('partners.manage', \App\Support\PermissionCatalog::forUser($request->user()), true);

        return Partners::all((int) $request->user()->foundation_id)->filter(fn ($p) => $all || $p->status === 'active')->map(fn ($p) => $p->toApi())->values()->all();
    }

    public function index(Request $request)
    {
        if ($request->user()->role === 'user') {
            return ApiResponse::error('FORBIDDEN', 'This account is not allowed to do that.', 403);
        }

        return ApiResponse::ok($this->list($request));
    }

    private function change(Request $request, string $code, string $to)
    {
        $data = $request->validate(['admin_password' => ['required', 'string', 'max:200']]);
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            Audit::record('partner.change_denied', $request, null, null, ['partner' => $code]);

            return ApiResponse::error('WRONG_PASSWORD', 'Your own password is not right.', 422, ['admin_password' => ['Your own password is not right.']]);
        }
        $p = Partners::byCode((int) $request->user()->foundation_id, $code);
        if (! $p) {
            return ApiResponse::notFound('No such partner.');
        }
        if ($p->status === 'not_setup') {
            return ApiResponse::error('PARTNER_NOT_SETUP', $p->name_en.' is not set up yet, so it cannot be paused or resumed.', 422);
        }
        if ($p->status === $to) {
            return ApiResponse::error('NO_CHANGE', $p->name_en.' is already '.($to === 'paused' ? 'paused' : 'working').'.', 422);
        }
        $p->update($to === 'paused' ? ['status' => 'paused', 'paused_at' => now(), 'paused_by' => $request->user()->id] : ['status' => 'active', 'paused_at' => null, 'paused_by' => null]);
        Audit::record($to === 'paused' ? 'partner.paused' : 'partner.resumed', $request, null, null, ['partner' => $p->code, 'name' => $p->name_en]);

        return ApiResponse::ok($this->list($request));
    }

    public function pause(Request $request, string $code)
    {
        return $this->change($request, $code, 'paused');
    }

    public function resume(Request $request, string $code)
    {
        return $this->change($request, $code, 'active');
    }
}
