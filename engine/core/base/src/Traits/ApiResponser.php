<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait ApiResponser
 *
 * ใช้สำหรับจัดการการตอบกลับของ API ให้เป็นมาตรฐานเดียวกัน
 * ทำหน้าที่ทดแทน/ทำงานร่วมกับ Response::macro เพื่อให้ Controller
 * สามารถเรียกใช้ $this->successResponse() หรือ $this->errorResponse() ได้ง่ายและ Test ง่ายขึ้น
 */
trait ApiResponser
{
    /**
     * คืนค่าการทำงานสำเร็จ
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (app()->environment('local')) {
            $options |= JSON_PRETTY_PRINT;
        }

        return response()->json([
            'status' => 'success',
            'message' => $message ?? 'Operation successful',
            'data' => $data,
        ], $code, [], $options);
    }

    /**
     * คืนค่าการทำงานล้มเหลว
     */
    protected function errorResponse(?string $message = null, int $code = 400, mixed $data = null): JsonResponse
    {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (app()->environment('local')) {
            $options |= JSON_PRETTY_PRINT;
        }

        return response()->json([
            'status' => 'error',
            'message' => $message ?? 'An error occurred',
            'data' => $data,
        ], $code, [], $options);
    }
}
