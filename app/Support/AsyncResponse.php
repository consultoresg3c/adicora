<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class AsyncResponse
{
    public static function success(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (object) $data,
            'errors' => (object) [],
            'meta' => (object) [],
        ], $status);
    }

    public static function error(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => (object) [],
            'errors' => (object) $errors,
            'meta' => (object) [],
        ], $status);
    }
}
