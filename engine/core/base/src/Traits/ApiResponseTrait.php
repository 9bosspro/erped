<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

trait ApiResponseTrait
{
    /**
     * ส่ง JSON response แบบ success ผ่าน Response macro
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการส่งกลับ
     * @param  string  $message  ข้อความแจ้งผู้ใช้
     * @param  int  $code  HTTP status code (default: 200)
     * @param  array<string, string>  $headers  HTTP headers เพิ่มเติม
     * @param  int  $options  JSON encode options
     */
    public function sendResponse(mixed $data, string $message = '', int $code = 200, array $headers = [], int $options = 0): JsonResponse
    {
        return Response::apiSuccessResponse($data, $message, $code, $headers, $options);
    }

    /**
     * ส่ง JSON response แบบ error ผ่าน Response macro
     *
     * @param  string  $message  ข้อความแจ้ง error
     * @param  int  $code  HTTP status code (default: 404)
     * @param  mixed  $data  ข้อมูล error เพิ่มเติม (optional)
     * @param  array<string, string>  $headers  HTTP headers เพิ่มเติม
     * @param  int  $options  JSON encode options
     */
    public function sendError(string $message = '', int $code = 404, mixed $data = null, array $headers = [], int $options = 0): JsonResponse
    {
        return Response::apiErrorResponse($message, $code, $data, $headers, $options);
    }

    /**
     * ส่ง JSON response จาก ServiceResult DTO โดยตรง
     *
     * @param  \Core\Base\DTO\ServiceResult<mixed>  $result  ผลลัพธ์จาก Service Layer
     * @param  array<string, string>  $headers  HTTP headers เพิ่มเติม
     * @param  int  $options  JSON encode options
     */
    public function sendServiceResult(\Core\Base\DTO\ServiceResult $result, array $headers = [], int $options = 0): JsonResponse
    {
        return $result->success
            ? $this->sendResponse($result->data, $result->message, $result->code, $headers, $options)
            : $this->sendError($result->message, $result->code, $result->data, $headers, $options);
    }
}
