<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a file has been permanently deleted from storage and DB.
 */
class FileDeleteCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $fileId,
        public readonly string $path,
        public readonly string $diskName,
        public readonly ?string $userId = null,
    ) {}
}
