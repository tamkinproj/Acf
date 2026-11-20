<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The staff app works offline and sends saved changes later. If a connection drops after the server did the work
 * but before the app heard back, the app sends the same change again with the same Idempotency-Key.
 * The server then answers with the stored result instead of doing it twice (no duplicate children, no double payments).
 */
class Idempotent
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        // Answers that carry a secret shown once (a new partner key) are never stored for replay.
        if ($request->is('api/system/api-keys', 'api/system/api-keys/*', 'api/users/*/password')) {
            return $next($request);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key)) {
            return ApiResponse::error('BAD_IDEMPOTENCY_KEY', 'The Idempotency-Key must be 8 to 100 letters, numbers, - or _.', 422);
        }

        $user = $request->user();
        $path = substr('/'.$request->path(), 0, 190);

        // Tidy up now and then: keys older than 7 days are useless.
        if (random_int(1, 50) === 1) {
            DB::table('idempotency_keys')->where('created_at', '<', now()->subDays(7))->delete();
        }

        $row = DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->first();

        if (! $row) {
            try {
                DB::table('idempotency_keys')->insert(['user_id' => $user->id, 'key' => $key, 'method' => $request->method(), 'path' => $path, 'created_at' => now()]);
            } catch (\Throwable $e) {
                $row = DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->first();
                if (! $row) {
                    throw $e;
                }
            }
        }

        if ($row) {
            if ($row->method !== $request->method() || $row->path !== $path) {
                return ApiResponse::error('IDEMPOTENCY_KEY_REUSED', 'This key was already used for a different request.', 422);
            }
            if ($row->status === null) {
                return ApiResponse::error('REQUEST_IN_PROGRESS', 'The same change is still being processed. Try again in a moment.', 409);
            }

            return response($row->body, $row->status, ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true']);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->delete();
            throw $e;
        }

        $json = str_contains((string) $response->headers->get('Content-Type'), 'json');
        if ($response->getStatusCode() < 500 && $response->getStatusCode() !== 429 && $json) {
            DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)
                ->update(['status' => $response->getStatusCode(), 'body' => $response->getContent()]);
        } else {
            // Server errors and non-JSON answers are not remembered, so the app may safely try again.
            DB::table('idempotency_keys')->where('user_id', $user->id)->where('key', $key)->delete();
        }

        return $response;
    }
}
