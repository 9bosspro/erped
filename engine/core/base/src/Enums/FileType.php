<?php

namespace Core\Base\Enums;

enum FileType: string
{
    case Document = 'document';
    case Image = 'image';
    case Video = 'video';
    case Sound = 'sound';
    case YouTube = 'youtube';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'เอกสาร',
            self::Image => 'รูปภาพ',
            self::Video => 'วิดีโอ',
            self::Sound => 'เสียง',
            self::YouTube => 'ยูทูบ',
            self::Unknown => 'ไม่ทราบประเภท',
        };
    }
}
