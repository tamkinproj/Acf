<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\PartnerContext;
use App\Support\Partners;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Orphans, applications and education work inside one partner. The app names it in the X-Partner header; without it, the
 * first active partner is used. A paused partner cannot be used by staff; the President Admin can still look (read only).
 * A partner that is not set up yet cannot be used at all. Runs before route-model binding, so a record of another partner is not found.
 */
class PartnerScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->foundation_id) {
            return $next($request);
        }
        $p = Partners::resolve((int) $user->foundation_id, $request->header('X-Partner'));
        if (! $p) {
            return ApiResponse::error('PARTNER_UNKNOWN', 'This partner is not known.', 404);
        }
        if ($p->status !== 'active') {
            $canLook = $p->status === 'paused' && in_array($request->method(), ['GET', 'HEAD'], true) && in_array('partners.manage', PermissionCatalog::forUser($user), true);
            if (! $canLook) {
                return $p->status === 'paused'
                    ? ApiResponse::error('PARTNER_PAUSED', $p->name_en.' is paused. Its work cannot be used until the President Admin resumes it.', 423)
                    : ApiResponse::error('PARTNER_NOT_SETUP', $p->name_en.' is not set up yet.', 423);
            }
        }

        PartnerContext::set($p);
        try {
            $response = $next($request);
        } finally {
            PartnerContext::set(null);
        }
        $response->headers->set('X-Partner', $p->code);
        if ($p->status === 'paused') {
            $response->headers->set('X-Partner-Paused', '1');
        }

        return $response;
    }
}
