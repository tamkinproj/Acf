<?php

namespace App\Http\Controllers\Api;

use App\Core\Foundation\LogoStorage;
use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class FoundationController extends Controller
{
    public function show(): JsonResponse
    {
        $f = Foundation::current();

        return ApiResponse::ok($f ? $f->toSyncPayload() + ['has_logo' => $f->logo_path !== null] : null);
    }

    /** Binary uploads can't be queued offline in Phase 1, so the logo is an online-only operation. */
    public function uploadLogo(Request $request, LogoStorage $logos): JsonResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:'.config('foundation.uploads.logo_max_kb')]]);
        $foundation = Foundation::current() ?? abort(404);

        try {
            $stored = $logos->store($request->file('logo'));
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_LOGO', $e->getMessage(), 422);
        }

        $old = $foundation->logo_path;
        $foundation->forceFill(['logo_path' => $stored['path'], 'logo_hash' => $stored['hash']])->save();
        $logos->delete($old);

        return ApiResponse::ok(['logo_hash' => $stored['hash']]);
    }

    public function deleteLogo(LogoStorage $logos): JsonResponse
    {
        $foundation = Foundation::current() ?? abort(404);
        $old = $foundation->logo_path;
        $foundation->forceFill(['logo_path' => null, 'logo_hash' => null])->save();
        $logos->delete($old);

        return ApiResponse::ok(null);
    }

    /** Public branding image (login page, offline shell). Served through PHP so storage stays private. */
    public function logo(LogoStorage $logos): Response
    {
        $f = Foundation::current();
        abort_unless($f && $logos->exists($f->logo_path), 404);

        return response($logos->contents($f->logo_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"'.$f->logo_hash.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
