<?php

use App\Http\Middleware\EnsureAccountUsable;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResetTenantContext;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveDevice;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // The JSON API shares the web session (same-origin PWA): cookie auth + CSRF, no tokens in localStorage.
            Route::middleware('web')->prefix('api')->group(base_path('routes/api.php'));
            Route::middleware('web')->prefix('install')->group(base_path('routes/install.php'));
            Route::middleware('web')->prefix('upgrade')->group(base_path('routes/upgrade.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(EnsureInstalled::class);
        $middleware->prepend(ResetTenantContext::class);
        // Route-model binding looks records up through the tenant scope, so the tenant must be known first.
        $middleware->appendToPriorityList(after: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, append: ResolveTenant::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'permission' => RequirePermission::class,
            'device' => ResolveDevice::class,
            'tenancy' => ResolveTenant::class,
            'account.usable' => EnsureAccountUsable::class,
        ]);
        $middleware->redirectGuestsTo(fn () => '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson();
        $exceptions->shouldRenderJsonWhen($isApi);
        // Never echo secrets back into forms after a failed validation.
        $exceptions->dontFlash(['db_password', 'admin_password', 'admin_password_confirmation', 'current_password', 'password', 'password_confirmation']);

        $exceptions->render(fn (ValidationException $e, Request $r) => $isApi($r)
            ? ApiResponse::error('VALIDATION_FAILED', 'The given data was invalid.', 422, $e->errors()) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $isApi($r)
            ? ApiResponse::error('UNAUTHENTICATED', 'Authentication required.', 401) : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $isApi($r)
            ? ApiResponse::error('NOT_FOUND', 'Not found.', 404) : null);
        $exceptions->render(fn (HttpExceptionInterface $e, Request $r) => $isApi($r) && $e->getStatusCode() >= 400
            ? ApiResponse::error($e->getStatusCode() === 429 ? 'THROTTLED' : 'HTTP_'.$e->getStatusCode(), $e->getMessage() ?: 'Request failed.', $e->getStatusCode()) : null);
    })->create();
