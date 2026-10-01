<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every request starts with no tenant: it must earn one (from the signed-in user, or an explicit public lookup). */
class ResetTenantContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenant->reset();

        return $next($request);
    }
}
