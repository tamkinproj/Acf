<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Maps every thrown exception onto the ApiResponse envelope so the mobile
 * client can distinguish 401 / 403 / 404 / 409 / 422 / 429 / 5xx reliably.
 *
 * Laravel 11+  -> bootstrap/app.php:
 *     ->withExceptions(fn ($exceptions) => ApiExceptions::register($exceptions))
 *
 * Laravel 10   -> app/Exceptions/Handler.php register():
 *     ApiExceptions::registerLegacy($this);
 */
final class ApiExceptions
{
    public static function register(object $exceptions): void
    {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return self::toResponse($e);
        });
    }

    public static function registerLegacy(object $handler): void
    {
        $handler->renderable(function (\Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return self::toResponse($e);
        });
    }

    public static function toResponse(\Throwable $e): \Illuminate\Http\JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'VALIDATION_FAILED',
                'The submitted data is invalid.',
                422,
                $e->errors()
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                'UNAUTHENTICATED',
                'Authentication required.',
                401
            ),
            $e instanceof AuthorizationException => ApiResponse::forbidden(),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::notFound(),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                'THROTTLED',
                'Too many requests. Retry later.',
                429
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                'HTTP_ERROR',
                $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                $e->getStatusCode()
            ),
            default => ApiResponse::error(
                'SERVER_ERROR',
                config('app.debug') ? $e->getMessage() : 'Unexpected server error.',
                500
            ),
        };
    }
}
