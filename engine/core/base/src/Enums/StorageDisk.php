<?php

namespace Core\Base\Enums;

enum StorageDisk: string
{
    case LOCAL = 'local';
    case PUBLIC = 'public';
    case MINIO = 'minio';
    case GOOGLE_DRIVE = 'google_drive';

    public function label(): string
    {
        return match ($this) {
            self::LOCAL => 'Local Storage',
            self::PUBLIC => 'Public Storage',
            self::MINIO => 'MinIO (S3)',
            self::GOOGLE_DRIVE => 'Google Drive',
        };
    }

    public function supportsSignedUrl(): bool
    {
        return match ($this) {
            self::MINIO => true,
            default => false,
        };
    }

    public function supportsPublicUrl(): bool
    {
        return match ($this) {
            self::PUBLIC, self::MINIO => true,
            default => false,
        };
    }
}
