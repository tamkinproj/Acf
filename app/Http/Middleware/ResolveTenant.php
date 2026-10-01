<?php

namespace App\Http\Middleware;

use App\Core\Settings\SettingsService;
use App\Models\Foundation;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides, from the signed-in user, which world the request lives in - and refuses to cross it.
 *
 *   tenancy:tenant    foundation users only (default)
 *   tenancy:platform  platform administrators only
 *   tenancy:any       either (sign-out, own profile, own password)
 *
 * A foundation that is not active locks its people out on the very next request, not at next login.
 * This runs after authentication and before anything touches tenant data.
 */
class ResolveTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $kind = 'tenant'): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        if ($user->foundation_id === null) {
            if ($kind === 'tenant') {
                return ApiResponse::error('FORBIDDEN', 'Platform accounts do not have access to foundation records.', 403);
            }
            $this->tenant->setPlatform();
        } else {
            if ($kind === 'platform') {
                return ApiResponse::error('FORBIDDEN', 'This area is only for platform administrators.', 403);
            }
            $foundation = $this->tenant->asSystem(fn () => Foundation::query()->find($user->foundation_id));
            if (! $foundation || ! $foundation->isActive()) {
                $status = $foundation?->status ?? 'unavailable';
                Auth::guard('web')->logout();
                $request->session()->invalidate();

                return ApiResponse::error('FOUNDATION_'.strtoupper($status), "This foundation is {$status}. Contact the platform administrator.", 403);
            }
            $this->tenant->setTenant($foundation->getKey());
        }

        // The user's relations may have been resolved before the context existed.
        $user->unsetRelation('role');
        app(SettingsService::class)->applyRuntime();

        return $next($request);
    }
}
