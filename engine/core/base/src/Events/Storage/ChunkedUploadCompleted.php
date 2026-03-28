<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when chunked upload is completed and merged.
 */
class ChunkedUploadCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $uploadId,
        public readonly string $path,
        public readonly array $uploadResult,
        public readonly int $totalChunks,
        public readonly int $totalSize,
        public readonly ?string $userId = null,
    ) {}

    /**
     * Get file ID from result.
     */
    public function getFileId(): ?string
    {
        return $this->uploadResult['id'] ?? null;
    }

    /**
     * Get human readable total size.
     */
    public function getHumanTotalSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($this->totalSize, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2).' '.$units[$pow];
    }
}
