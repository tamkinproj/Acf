<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate for endpoints meant to be called by outside systems using a key
 * issued by the System Admin, instead of a logged-in user's
 * Sanctum token. Not applied to any route yet - register it (route
 * middleware alias 'api_key', see Kernel.php) on whichever specific
 * endpoints a partner integration actually needs once that's known,
 * rather than opening the whole API surface to key-based auth up front.
 *
 * Usage: Route::middleware('api_key')->post('/partner/...', ...);
 * The key travels in the X-Api-Key header. On success, $request->apiKey
 * carries the resolved ApiKey model so the controller can check
 * ->abilities or ->foundation_id if the endpoint needs to scope by them.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $raw = $request->header('X-Api-Key');

        if (! $raw) {
            return response()->json(['message' => 'Missing API key.'], 401);
        }

        $key = ApiKey::where('key_hash', hash('sha256', $raw))->first();

        if (! $key || ! $key->isActive()) {
            return response()->json(['message' => 'Invalid or revoked API key.'], 401);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('apiKey', $key);

        return $next($request);
    }
}
