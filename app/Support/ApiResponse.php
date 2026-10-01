<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * One response envelope for the whole API (pattern from MuslimEdu's ApiResponse):
 *   success: {"success":true,"data":...,"meta":{...}}
 *   failure: {"success":false,"code":"MACHINE_CODE","message":"...","errors":{...}}
 * Empty collections are 200 with data: [] - never 404.
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data] + ($meta ? ['meta' => $meta] : []), $status);
    }

    public static function created(mixed $data = null, array $meta = []): JsonResponse
    {
        return self::ok($data, $meta, 201);
    }

    public static function error(string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['success' => false, 'code' => $code, 'message' => $message] + ($errors ? ['errors' => $errors] : []), $status);
    }
}
