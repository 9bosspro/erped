<?php

declare(strict_types=1);

namespace Core\Base\Exceptions\Storage;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * FileNotDeletedException — ไฟล์ยังไม่ถูก soft delete ไม่สามารถ force delete ได้
 *
 * HTTP 400 Bad Request — client ต้อง soft delete ก่อนจึงจะ force delete ได้
 *
 * การใช้งาน:
 * ```php
 * throw new FileNotDeletedException();
 * // → HTTP 400: "ไฟล์นี้เป็นไฟล์ปกติ ต้อง soft delete ก่อนจึงจะ force delete ได้"
 * ```
 */
final class FileNotDeletedException extends HttpException
{
    public function __construct(
        string $message = 'ไฟล์นี้เป็นไฟล์ปกติ ต้อง soft delete ก่อนจึงจะ force delete ได้',
        ?Throwable $previous = null,
    ) {
        parent::__construct(400, $message, $previous);
    }
}
