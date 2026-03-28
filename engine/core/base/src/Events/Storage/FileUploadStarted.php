<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when file upload starts.
 */
class FileUploadStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $filename,
        public readonly string $directory,
        public readonly int $fileSize,
        public readonly string $mimeType,
        public readonly ?string $userId = null,
        public readonly string $driver = 'minio',
    ) {}

    /**
     * Get human readable file size.
     */
    public function getHumanFileSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($this->fileSize, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2).' '.$units[$pow];
    }
}
