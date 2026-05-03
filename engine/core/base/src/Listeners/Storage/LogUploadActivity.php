<?php

declare(strict_types=1);

namespace Core\Base\Listeners\Storage;

use Core\Base\Events\Storage\ChunkedUploadCompleted;
use Core\Base\Events\Storage\FileUploadCompleted;
use Core\Base\Events\Storage\FileUploadFailed;
use Core\Base\Events\Storage\FileUploadStarted;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Listener to log file upload activity.
 */
class LogUploadActivity
{
    /**
     * Handle upload started.
     */
    public function handleStarted(FileUploadStarted $event): void
    {
        Log::channel('storage')->info('FILE_UPLOAD_STARTED', [
            'filename' => $event->filename,
            'directory' => $event->directory,
            'size' => $event->getHumanFileSize(),
            'mime_type' => $event->mimeType,
            'user_id' => $event->userId,
            'driver' => $event->driver,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle upload completed.
     */
    public function handleCompleted(FileUploadCompleted $event): void
    {
        Log::channel('storage')->info('FILE_UPLOAD_COMPLETED', [
            'file_id' => $event->getFileId(),
            'path' => $event->getFilePath(),
            'size' => $event->getFileSize(),
            'user_id' => $event->userId,
            'driver' => $event->driver,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle upload failed.
     */
    public function handleFailed(FileUploadFailed $event): void
    {
        Log::channel('storage')->error('FILE_UPLOAD_FAILED', [
            'filename' => $event->filename,
            'directory' => $event->directory,
            'reason' => $event->reason,
            'error' => $event->getErrorMessage(),
            'user_id' => $event->userId,
            'driver' => $event->driver,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle chunked upload completed.
     */
    public function handleChunkedCompleted(ChunkedUploadCompleted $event): void
    {
        Log::channel('storage')->info('CHUNKED_UPLOAD_COMPLETED', [
            'upload_id' => $event->uploadId,
            'file_id' => $event->getFileId(),
            'path' => $event->path,
            'total_chunks' => $event->totalChunks,
            'total_size' => $event->getHumanTotalSize(),
            'user_id' => $event->userId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    /**
     * ลงทะเบียน listeners สำหรับ subscriber
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            FileUploadStarted::class => 'handleStarted',
            FileUploadCompleted::class => 'handleCompleted',
            FileUploadFailed::class => 'handleFailed',
            ChunkedUploadCompleted::class => 'handleChunkedCompleted',
        ];
    }
}
