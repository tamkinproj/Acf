<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate (Phase F1).
 *
 * Usage: ->middleware(['auth:sanctum', 'role:admin'])
 *        ->middleware(['auth:sanctum', 'role:president_admin,staff'])
 *
 * Fails closed: unknown or unresolvable role => 403.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->deny('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $allowed = array_filter(array_map(fn ($role) => $this->canonicalise($role), $allowed));
        $roles = $this->rolesFor($user);

        if ($allowed === [] || array_intersect($roles, $allowed) === []) {
            return $this->deny('FORBIDDEN', 'This account is not allowed to access this resource.', 403);
        }

        $request->attributes->set('resolved_role', $roles[0] ?? null);

        return $next($request);
    }

    /** @return string[] */
    protected function rolesFor(object $user): array
    {
        $roles = [];

        foreach ((array) config('roles.attributes', ['role']) as $attribute) {
            $value = $user->{$attribute} ?? null;

            if (is_string($value) || is_int($value)) {
                $roles[] = $this->canonicalise((string) $value);
            }
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                foreach ($user->getRoleNames() as $name) {
                    $roles[] = $this->canonicalise((string) $name);
                }
            } catch (\Throwable) {
                // Permission package not installed or misconfigured: ignore.
            }
        } elseif (method_exists($user, 'roles')) {
            try {
                foreach ($user->roles()->pluck('name') as $name) {
                    $roles[] = $this->canonicalise((string) $name);
                }
            } catch (\Throwable) {
                // Relation missing: ignore.
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    protected function canonicalise(string $value): string
    {
        $value = strtolower(trim($value));
        $aliases = (array) config('roles.aliases', []);

        return $aliases[$value] ?? $value;
    }

    protected function deny(string $code, string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
