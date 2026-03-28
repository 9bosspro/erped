<?php

declare(strict_types=1);

namespace Core\Base\Exceptions\Storage;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * FileAlreadyTrashedException — ไฟล์ถูก soft-delete แล้ว ไม่สามารถ upload ซ้ำได้
 *
 * HTTP 409 Conflict — client ควรแจ้งผู้ใช้ว่าไฟล์อยู่ในถังขยะ
 * และให้ restore ก่อนจึงจะ upload ซ้ำได้
 *
 * ต่างจาก RuntimeException ตรงที่:
 * - HTTP status ชัดเจน (409 แทน 500)
 * - Handler.php จะ render เป็น JSON response ที่ถูกต้องโดยอัตโนมัติ
 * - Client รู้ว่าต้องทำอะไรต่อ (restore ไม่ใช่ retry)
 */
final class FileAlreadyTrashedException extends HttpException
{
    public function __construct(
        string $identifier = '',
        ?Throwable $previous = null,
    ) {
        $message = $identifier !== ''
            ? "ไฟล์ '{$identifier}' ถูกลบเข้าถังขยะแล้ว กรุณา restore ก่อน upload ซ้ำ"
            : 'ไฟล์ถูกลบเข้าถังขยะแล้ว กรุณา restore ก่อน upload ซ้ำ';

        parent::__construct(409, $message, $previous);
    }
}
