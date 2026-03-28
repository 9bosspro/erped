<?php

declare(strict_types=1);

namespace Core\Base\Exceptions\Storage;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * StorageDiskNotFoundException — ไม่พบ storage disk ที่ระบุใน configuration
 *
 * HTTP 422 Unprocessable Content — client ส่ง driver name ที่ไม่มีในระบบ
 * ปัญหาอยู่ที่ input ของ request ไม่ใช่ server error (ไม่ควรเป็น 500)
 *
 * การใช้งาน:
 * ```php
 * throw new StorageDiskNotFoundException('s3-invalid');
 * // → HTTP 422: "Storage disk 's3-invalid' ไม่พบในระบบ ..."
 * ```
 */
final class StorageDiskNotFoundException extends HttpException
{
    public function __construct(
        string $diskName,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            422,
            "Storage disk '{$diskName}' ไม่พบในระบบ กรุณาตรวจสอบ configuration",
            $previous,
        );
    }
}
