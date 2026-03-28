<?php

declare(strict_types=1);

namespace Core\Base\Exceptions\Storage;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * FileAlreadyDeletedException — ไฟล์ถูก soft delete ไปแล้ว ไม่สามารถ soft delete ซ้ำได้
 *
 * HTTP 400 Bad Request — client ต้องใช้ force delete แทน
 *
 * ต่างจาก FileAlreadyTrashedException (409):
 * - TrashedException → ไฟล์อยู่ในถังขยะ ให้ restore ก่อน upload ซ้ำ
 * - DeletedException → ไฟล์ถูก soft delete แล้ว ให้ใช้ force delete แทน
 *
 * การใช้งาน:
 * ```php
 * throw new FileAlreadyDeletedException();
 * // → HTTP 400: "ไฟล์นี้ถูกลบไปแล้ว (soft deleted) ใช้ force delete เพื่อลบถาวร"
 * ```
 */
final class FileAlreadyDeletedException extends HttpException
{
    public function __construct(
        string $message = 'ไฟล์นี้ถูกลบไปแล้ว (soft deleted) ใช้ force delete เพื่อลบถาวร',
        ?Throwable $previous = null,
    ) {
        parent::__construct(400, $message, $previous);
    }
}
