<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use App\Models\StorageFiles;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when file upload is completed successfully.
 */
class FileUploadCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly StorageFiles $file,
        public readonly array $uploadMetadata,
        public readonly ?string $userId = null,
        public readonly string $driver = 'minio',
    ) {}

    /**
     * Get file ID.
     */
    public function getFileId(): string
    {
        return $this->file->id;
    }

    /**
     * Get file path.
     */
    public function getFilePath(): string
    {
        return $this->uploadMetadata['path'] ?? $this->file->path;
    }

    /**
     * Get file URL.
     */
    public function getFileUrl(): ?string
    {
        return $this->uploadMetadata['url'] ?? null;
    }

    /**
     * Get file size.
     */
    public function getFileSize(): int
    {
        return $this->uploadMetadata['size'] ?? $this->file->size ?? 0;
    }
}
