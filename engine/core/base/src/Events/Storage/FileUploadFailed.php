<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when file upload fails.
 */
class FileUploadFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $filename,
        public readonly string $directory,
        public readonly string $reason,
        public readonly ?Throwable $exception = null,
        public readonly ?string $userId = null,
        public readonly string $driver = 'minio',
    ) {}

    /**
     * Get error message.
     */
    public function getErrorMessage(): string
    {
        if ($this->exception) {
            return $this->exception->getMessage();
        }

        return $this->reason;
    }
}
