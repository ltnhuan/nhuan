<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(array|object|null $data = [], string $message = 'Thao tác thành công', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? [],
            'meta' => $meta,
        ], $status);
    }

    public static function error(string $message = 'Không thể thực hiện thao tác', array $errors = [], string $code = 'ACTION_FAILED', int $status = 400, array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
            'meta' => $meta,
        ], $status);
    }

    public static function validationError(array $errors, string $message = 'Dữ liệu không hợp lệ'): JsonResponse
    {
        return self::error($message, $errors, 'VALIDATION_FAILED', 422);
    }

    public static function forbidden(string $message = 'Bạn không có quyền thực hiện thao tác này'): JsonResponse
    {
        return self::error($message, [], 'FORBIDDEN', 403);
    }

    public static function notFound(string $message = 'Không tìm thấy dữ liệu'): JsonResponse
    {
        return self::error($message, [], 'NOT_FOUND', 404);
    }

    public static function serverError(string $message = 'Lỗi hệ thống'): JsonResponse
    {
        return self::error($message, [], 'SERVER_ERROR', 500);
    }
}
