<?php

declare(strict_types=1);

namespace Core\Base\Listeners\Storage;

use Core\Base\Events\Storage\ChunkedUploadCompleted;
use Core\Base\Events\Storage\FileUploadCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Listener to notify when upload is complete.
 * Can be extended to send notifications via various channels.
 */
class NotifyUploadComplete implements ShouldQueue
{
    /**
     * The queue name.
     */
    public string $queue = 'notifications';

    /**
     * Handle file upload completed.
     */
    public function handleFileUpload(FileUploadCompleted $event): void
    {
        if (! $event->userId) {
            return;
        }

        // Broadcast or notify user
        // This can be extended to use Laravel Notifications
        Log::debug('File upload notification', [
            'user_id' => $event->userId,
            'file_id' => $event->getFileId(),
            'path' => $event->getFilePath(),
        ]);
    }

    /**
     * Handle chunked upload completed.
     */
    public function handleChunkedUpload(ChunkedUploadCompleted $event): void
    {
        if (! $event->userId) {
            return;
        }

        Log::debug('Chunked upload notification', [
            'user_id' => $event->userId,
            'upload_id' => $event->uploadId,
            'file_id' => $event->getFileId(),
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
            FileUploadCompleted::class => 'handleFileUpload',
            ChunkedUploadCompleted::class => 'handleChunkedUpload',
        ];
    }

    /**
     * กำหนดว่า listener ควร queue หรือไม่
     */
    public function shouldQueue(FileUploadCompleted|ChunkedUploadCompleted $event): bool
    {
        return (bool) config('core.events.queue_notifications', true);
    }
}
