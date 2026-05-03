<?php

declare(strict_types=1);

namespace Core\Base\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = 'data';

    protected string $message = 'Operation successful.';

    protected string $status = 'success';

    protected int $httpCode = 200;

    /** @var array<string, mixed> */
    protected array $meta = [];

    /**
     * กำหนด message, status และ HTTP code ของ response
     */
    public function withMessage(
        string $message,
        string $status = 'success',
        int $httpCode = 200,
    ): static {
        $this->message = $message;
        $this->status = $status;
        $this->httpCode = $httpCode;

        return $this;
    }

    /**
     * กำหนด response สำเร็จพร้อม message และ HTTP code
     */
    public function withSuccess(
        string $message = 'Operation successful.',
        int $httpCode = 200,
    ): static {
        return $this->withMessage($message, 'success', $httpCode);
    }

    /**
     * กำหนด response ผิดพลาดพร้อม message และ HTTP code
     */
    public function withError(
        string $message = 'Operation failed.',
        int $httpCode = 400,
    ): static {
        return $this->withMessage($message, 'error', $httpCode);
    }

    /**
     * เพิ่ม metadata เพิ่มเติมใน response
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge(
            $this->meta,
            $meta,
        );

        return $this;
    }

    /**
     * สร้าง wrapper มาตรฐานสำหรับ response
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $wrapper = [
            'status' => $this->status,
            'message' => $this->message,
            'code' => $this->httpCode,
        ];

        if (! empty($this->meta)) {
            $wrapper['meta'] = $this->meta;
        }

        return $wrapper;
    }

    /**
     * กำหนด HTTP status code ของ JsonResponse
     */
    public function toResponse($request): JsonResponse
    {
        return parent::toResponse($request)
            ->setStatusCode($this->httpCode);
    }
}
