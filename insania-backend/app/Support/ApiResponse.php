<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

/**
 * Single response envelope for the whole API (Phase F2).
 *
 * Rules enforced here:
 *  - reads             => 200
 *  - creates           => 201
 *  - updates           => 200
 *  - deletes           => 200 with success payload (never a bare 204 body)
 *  - EMPTY COLLECTIONS => 200 with data: []  (never 400, never 404)
 *  - errors            => machine-readable code + message + optional errors
 */
final class ApiResponse
{
    public static function ok(mixed $data = null, array $meta = []): JsonResponse
    {
        return self::payload(true, $data, $meta, 200);
    }

    public static function created(mixed $data = null, array $meta = []): JsonResponse
    {
        return self::payload(true, $data, $meta, 201);
    }

    public static function updated(mixed $data = null, array $meta = []): JsonResponse
    {
        return self::payload(true, $data, $meta, 200);
    }

    public static function deleted(string $message = 'Deleted.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
        ], 200);
    }

    /**
     * Normalises paginators, collections and plain arrays into one shape.
     * An empty result is a successful 200, not an error.
     */
    public static function collection(mixed $items, array $meta = []): JsonResponse
    {
        if ($items instanceof AbstractPaginator || $items instanceof Paginator) {
            /** @var \Illuminate\Pagination\LengthAwarePaginator $items */
            return response()->json([
                'success' => true,
                'data' => array_values($items->items()),
                'meta' => array_merge([
                    'page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => method_exists($items, 'total') ? $items->total() : null,
                    'last_page' => method_exists($items, 'lastPage') ? $items->lastPage() : null,
                    'empty' => count($items->items()) === 0,
                ], $meta),
            ], 200);
        }

        $data = $items instanceof Collection ? $items->values()->all() : array_values((array) $items);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'page' => 1,
                'per_page' => count($data),
                'total' => count($data),
                'last_page' => 1,
                'empty' => $data === [],
            ], $meta),
        ], 200);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $body = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error('FORBIDDEN', $message, 403);
    }

    public static function notFound(string $message = 'Not found.'): JsonResponse
    {
        return self::error('NOT_FOUND', $message, 404);
    }

    private static function payload(bool $success, mixed $data, array $meta, int $status): JsonResponse
    {
        $body = ['success' => $success, 'data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }
}
