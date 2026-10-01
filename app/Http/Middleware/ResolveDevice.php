<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\ApiResponse;
use App\Sync\DeviceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Device authentication. A device proves itself with `Authorization: Bearer fdt_...`;
 * only the sha256 of the token is stored (the ApiKeyAuth pattern from MuslimEdu).
 *
 * `device` (optional): a token, if sent, must be valid; none is fine.
 * `device:required`: sync endpoints - no valid token, no entry.
 * This is the second factor next to the user session: the user says who, the device says where.
 */
class ResolveDevice
{
    public function __construct(private DeviceContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $token = $request->bearerToken();
        $prefix = (string) config('foundation.device.token_prefix');

        if ($token === null || $token === '') {
            return $mode === 'required'
                ? ApiResponse::error('DEVICE_REQUIRED', 'This request must come from a registered device.', 401)
                : $next($request);
        }

        $device = str_starts_with($token, $prefix)
            ? Device::query()->where('token_hash', hash('sha256', $token))->first()
            : null;

        if (! $device || $device->isRevoked()) {
            return ApiResponse::error('DEVICE_INVALID', 'This device is not registered or has been revoked.', 401);
        }

        $this->context->setDevice($device);
        $request->attributes->set('device', $device);

        // Heartbeat, at most once a minute so reads don't turn into writes.
        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            Device::withoutEvents(fn () => $device->forceFill([
                'last_seen_at' => now(),
                'app_version' => substr((string) $request->header('X-App-Version', $device->app_version), 0, 20) ?: null,
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ])->save());
        }

        return $next($request);
    }
}
