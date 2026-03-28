<?php

declare(strict_types=1);

namespace Core\Base\Events\Storage;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when chunked upload session is initialized.
 */
class ChunkedUploadInitialized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $uploadId,
        public readonly string $filename,
        public readonly string $directory,
        public readonly ?string $mimeType = null,
        public readonly ?string $userId = null,
    ) {}
}
