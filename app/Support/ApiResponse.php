<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<int|string, mixed>  $meta
     */
    public static function success(
        string $message,
        array $data = [],
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => (object) [],
        ], $status);
    }

    /**
     * @param  array<int|string, mixed>  $errors
     * @param  array<int|string, mixed>  $meta
     * @param  array<int|string, mixed>  $data
     */
    public static function error(
        string $message,
        int $status,
        array $errors = [],
        array $meta = [],
        array $data = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => empty($errors) ? (object) [] : $errors,
        ], $status);
    }
}
