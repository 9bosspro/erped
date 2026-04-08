<?php

declare(strict_types=1);

namespace Core\Base\Jobs\Storage;

use Core\Base\Events\Storage\FileUploadCompleted;
use Core\Base\Events\Storage\FileUploadFailed;
use Core\Base\Repositories\Files\StorageFileRepository;
use Core\Base\Services\Storage\DriverResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessFileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $storageFileId,
        public readonly string $tempPath,    // path ของ temp file ใน local disk
        public readonly string $diskName,    // ชื่อ disk ปลายทาง เช่น 'minio'
        public readonly string $storagePath, // path ปลายทางใน disk นั้น
        public readonly string $directory,
        public readonly string $storedName,
        public readonly ?string $userId = null,
    ) {
        $this->onQueue('default'); // default  uploads
    }

    public function handle(
        StorageFileRepository $storageFileRepo,
        DriverResolverService $driverResolver,
    ): void {
        $storageFile = $storageFileRepo->find($this->storageFileId);

        if (! $storageFile) {
            Log::warning('ProcessFileUploadJob: StorageFile not found', ['id' => $this->storageFileId]);
            $this->cleanupTemp();

            return;
        }
        if (! Storage::disk('localtmp')->exists($this->tempPath)) {
            Log::warning('ProcessFileUploadJob: Temp file not found', ['path' => $this->tempPath]);

            return;
            //  throw new \RuntimeException("Temp file not found: {$this->tempPath}");
        }

        $tempAbsPath = Storage::disk('localtmp')->path($this->tempPath);
        $stream = null;
        //
        Log::info('File upload queued', ['storage_file_id' => $storageFile->id, 'path' => $this->storagePath]);

        try {

            $driver = $driverResolver->forDisk($this->diskName);

            if (! $driver->exists($this->storagePath)) {
                $stream = fopen($tempAbsPath, 'r');
                if ($stream === false) {
                    throw new RuntimeException("Cannot open temp file stream: {$this->tempPath}");
                }
                Storage::disk($this->diskName)->writeStream($this->storagePath, $stream);
                fclose($stream);
                $stream = null;
            }

            // สำรองไฟล์ไว้ backup-local ทุกครั้ง (ไม่ขึ้นกับว่า MinIO มีอยู่แล้วหรือเปล่า)
            if (! Storage::disk('backuplocal')->exists('uploads/'.$this->storedName)) {
                Storage::disk('backuplocal')->putFileAs(
                    'uploads',
                    new \Illuminate\Http\File($tempAbsPath),
                    $this->storedName,
                );
                Log::info('ProcessFileUploadJob backup-local created', [
                    'storageFileId' => $this->storageFileId,
                    'disk' => $this->diskName,
                    'path' => $this->storagePath,
                    'backup-local' => $this->storedName,
                    'tempAbsPath' => $tempAbsPath,
                ]);

            }
            // 3. อัปเดตสถานะความสำเร็จในฐานข้อมูล
            //    $this->updateStatus($storageFile, 'completed');

            $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                'upload_status' => 'completed',
                'uploaded_at' => now()->toISOString(true),
            ]);
            $storageFile->is_active = true;
            $storageFile->save();

            event(new FileUploadCompleted(
                file: $storageFile,
                uploadMetadata: ['path' => $this->storagePath],
                userId: $this->userId,
                driver: $this->diskName,
            ));

        } catch (Throwable $e) {
            Log::error('ProcessFileUploadJob failed', [
                'storageFileId' => $this->storageFileId,
                'disk' => $this->diskName,
                'path' => $this->storagePath,
                'temp' => $this->tempPath,
                'error' => $e->getMessage(),
            ]);
            /*   Log::error('ProcessFileUploadJob failed', [
                  'storageFileId' => $this->storageFileId,
                  'error' => $e->getMessage(),
              ]); */
            //     $this->updateStatus($storageFile, 'failed', $e->getMessage());

            $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                'upload_status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            $storageFile->save();

            event(new FileUploadFailed(
                filename: $this->storedName,
                directory: $this->directory,
                reason: $e->getMessage(),
                exception: $e,
                userId: $this->userId,
                driver: $this->diskName,
            ));

            throw $e;
        } finally {
            $this->cleanupTemp();
        }
    }

    /**
     * เรียกโดย Laravel เมื่อ Job ล้มเหลวครบ $tries แล้ว (permanent failure)
     *
     * ณ จุดนี้ไม่มีการ retry อีกต่อไป — อัปเดต status เป็น 'permanently_failed'
     * และ fire event เพื่อแจ้งเตือน (เช่น Slack, email, monitoring)
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessFileUploadJob: permanently failed after all retries', [
            'storageFileId' => $this->storageFileId,
            'disk' => $this->diskName,
            'path' => $this->storagePath,
            'error' => $exception->getMessage(),
        ]);

        try {
            $storageFileRepo = app(StorageFileRepository::class);
            $storageFile = $storageFileRepo->find($this->storageFileId);

            if ($storageFile) {
                $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                    'upload_status' => 'permanently_failed',
                    'failed_at' => now()->toISOString(true),
                    'error' => $exception->getMessage(),
                ]);
                $storageFile->is_active = false;
                $storageFile->save();
            }
        } catch (Throwable $e) {
            Log::error('ProcessFileUploadJob: failed to update status on permanent failure', [
                'storageFileId' => $this->storageFileId,
                'error' => $e->getMessage(),
            ]);
        }

        event(new FileUploadFailed(
            filename: $this->storedName,
            directory: $this->directory,
            reason: $exception->getMessage(),
            exception: $exception,
            userId: $this->userId,
            driver: $this->diskName,
        ));
    }

    public function backoff(): array
    {
        return [10, 30, 60]; // เพิ่มเวลาถอยห่างเมื่อเกิด Error
    }

    public function uniqueId(): string
    {
        return $this->storageFileId;
    }

    private function cleanupTemp(): void
    {
        try {
            if (Storage::disk('localtmp')->exists($this->tempPath)) {
                Storage::disk('localtmp')->delete($this->tempPath);
            }
        } catch (Throwable) {
            Log::warning('ProcessFileUploadJob: cleanup temp failed', ['path' => $this->tempPath]);
        }
    }
}
