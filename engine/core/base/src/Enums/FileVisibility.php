<?php

namespace Core\Base\Enums;

enum FileVisibility: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';
    case SHARED = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::PRIVATE => 'ส่วนตัว',
            self::PUBLIC => 'สาธารณะ',
            self::SHARED => 'แชร์',
        };
    }
}
