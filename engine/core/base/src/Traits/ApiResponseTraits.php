<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

trait ApiResponseTraits
{
    /**
     * ส่ง JSON response แบบ success ผ่าน Response macro
     *
     * @param  mixed  $result  ข้อมูลที่ต้องการส่งกลับ
     * @param  string  $message  ข้อความแจ้งผู้ใช้
     * @param  int  $code  HTTP status code (default: 200)
     * @param  array  $headers  HTTP headers เพิ่มเติม
     * @param  int  $options  JSON encode options
     */
    public function sendResponse(mixed $result, string $message = '', int $code = 200, array $headers = [], int $options = 0): JsonResponse
    {
        return Response::apiSuccessResponse($result, $message, $code, $headers, $options);
    }

    /**
     * ส่ง JSON response แบบ error ผ่าน Response macro
     *
     * @param  string  $errorMessages  ข้อความแจ้ง error
     * @param  int  $code  HTTP status code (default: 404)
     * @param  mixed  $error  ข้อมูล error เพิ่มเติม (optional)
     * @param  array  $headers  HTTP headers เพิ่มเติม
     * @param  int  $options  JSON encode options
     */
    public function sendError(string $errorMessages = '', int $code = 404, mixed $error = null, array $headers = [], int $options = 0): JsonResponse
    {
        return Response::apiErrorResponse($errorMessages, $code, $error, $headers, $options);
    }
}
