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

class ProcessFileUpdateUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $storageFileId,
        public readonly string $tempPath,        // path ของ temp file ใน localtmp disk
        public readonly string $diskName,        // ชื่อ disk ปลายทาง เช่น 'minio'
        public readonly string $newStoragePath,  // path ปลายทางของไฟล์ใหม่
        public readonly string $oldStoragePath,  // path ของไฟล์เก่าที่ต้องลบ
        public readonly string $storedName,
        public readonly ?string $userId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        StorageFileRepository $storageFileRepo,
        DriverResolverService $driverResolver,
    ): void {
        $storageFile = $storageFileRepo->find($this->storageFileId);

        if (! $storageFile) {
            Log::warning('ProcessFileUpdateUploadJob: StorageFile not found', ['id' => $this->storageFileId]);
            $this->cleanupTemp();

            return;
        }

        if (! Storage::disk('localtmp')->exists($this->tempPath)) {
            Log::warning('ProcessFileUpdateUploadJob: Temp file not found', ['path' => $this->tempPath]);

            return;
        }

        $tempAbsPath = Storage::disk('localtmp')->path($this->tempPath);
        $stream = null;

        try {
            $driver = $driverResolver->forDisk($this->diskName);

            // 1. อัปโหลดไฟล์ใหม่ไปยัง disk ปลายทาง
            if (! $driver->exists($this->newStoragePath)) {
                $stream = fopen($tempAbsPath, 'r');
                if ($stream === false) {
                    throw new RuntimeException("Cannot open temp file stream: {$this->tempPath}");
                }
                Storage::disk($this->diskName)->writeStream($this->newStoragePath, $stream);
                fclose($stream);
                $stream = null;
            }

            // 2. สำรองไฟล์ใหม่ไว้ backup-local
            if (! Storage::disk('backuplocal')->exists('uploads/'.$this->storedName)) {
                Storage::disk('backuplocal')->putFileAs(
                    'uploads',
                    new \Illuminate\Http\File($tempAbsPath),
                    $this->storedName,
                );
            }

            // 3. ลบไฟล์เก่าออกจาก disk (ถ้า path เปลี่ยน)
            if ($this->oldStoragePath !== $this->newStoragePath) {
                $this->safeDeleteFromDisk($this->diskName, $this->oldStoragePath);
                $this->safeDeleteFromDisk('backuplocal', 'uploads/'.$this->oldStoragePath);
            }

            // 4. อัปเดตสถานะสำเร็จในฐานข้อมูล
            $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                'upload_status' => 'completed',
                'updated_at' => now()->toISOString(true),
            ]);
            $storageFile->is_active = true;
            $storageFile->save();

            event(new FileUploadCompleted(
                file: $storageFile,
                uploadMetadata: ['path' => $this->newStoragePath],
                userId: $this->userId,
                driver: $this->diskName,
            ));

            Log::info('ProcessFileUpdateUploadJob: completed', [
                'id' => $this->storageFileId,
                'disk' => $this->diskName,
                'new_path' => $this->newStoragePath,
                'old_path' => $this->oldStoragePath,
            ]);

        } catch (Throwable $e) {
            Log::error('ProcessFileUpdateUploadJob failed', [
                'storageFileId' => $this->storageFileId,
                'disk' => $this->diskName,
                'new_path' => $this->newStoragePath,
                'error' => $e->getMessage(),
            ]);

            $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                'upload_status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            $storageFile->save();

            event(new FileUploadFailed(
                filename: $this->storedName,
                directory: dirname($this->newStoragePath),
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
     * Restore ไฟล์กลับ path เก่า + is_active = true
     * เพื่อไม่ให้ user เห็นไฟล์ที่อัปเดตล้มเหลว
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessFileUpdateUploadJob: permanently failed after all retries', [
            'storageFileId' => $this->storageFileId,
            'disk' => $this->diskName,
            'new_path' => $this->newStoragePath,
            'old_path' => $this->oldStoragePath,
            'error' => $exception->getMessage(),
        ]);

        try {
            $storageFileRepo = app(StorageFileRepository::class);
            $storageFile = $storageFileRepo->find($this->storageFileId);

            if ($storageFile) {
                // Restore กลับ path เดิม ให้ระบบยังใช้งานได้
                $storageFile->path = $this->oldStoragePath;
                $storageFile->is_active = true;
                $storageFile->metadata = array_merge($storageFile->metadata ?? [], [
                    'upload_status' => 'permanently_failed',
                    'failed_at' => now()->toISOString(true),
                    'error' => $exception->getMessage(),
                    'restored_to' => $this->oldStoragePath,
                ]);
                $storageFile->save();
            }
        } catch (Throwable $e) {
            Log::error('ProcessFileUpdateUploadJob: failed to restore status on permanent failure', [
                'storageFileId' => $this->storageFileId,
                'error' => $e->getMessage(),
            ]);
        }

        event(new FileUploadFailed(
            filename: $this->storedName,
            directory: dirname($this->newStoragePath),
            reason: $exception->getMessage(),
            exception: $exception,
            userId: $this->userId,
            driver: $this->diskName,
        ));
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return $this->storageFileId;
    }

    private function safeDeleteFromDisk(string $disk, string $path): void
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (Throwable $e) {
            Log::warning("ProcessFileUpdateUploadJob: delete from [{$disk}] failed", [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanupTemp(): void
    {
        try {
            if (Storage::disk('localtmp')->exists($this->tempPath)) {
                Storage::disk('localtmp')->delete($this->tempPath);
            }
        } catch (Throwable) {
            Log::warning('ProcessFileUpdateUploadJob: cleanup temp failed', ['path' => $this->tempPath]);
        }
    }
}
