<?php

declare(strict_types=1);

namespace Core\Base\Exceptions\Storage;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * FileNotFoundException — ไม่พบไฟล์ในระบบ
 *
 * HTTP 404 Not Found — ค้นหาไฟล์จาก ID แล้วไม่พบทั้งใน active และ trashed
 *
 * การใช้งาน:
 * ```php
 * throw new FileNotFoundException('abc-uuid');
 * // → HTTP 404: "ไม่พบไฟล์: abc-uuid"
 * ```
 */
final class FileNotFoundException extends HttpException
{
    public function __construct(
        string $identifier = '',
        ?Throwable $previous = null,
    ) {
        $message = $identifier !== ''
            ? "ไม่พบไฟล์: {$identifier}"
            : 'ไม่พบไฟล์ในระบบ';

        parent::__construct(404, $message, $previous);
    }
}
