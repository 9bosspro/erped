<?php

declare(strict_types=1);

namespace Core\Base\Jobs\Storage;

use Core\Base\Contracts\FileStorage\StorageDriverInterface;
use Core\Base\Events\Storage\FileDeleteCompleted;
use Core\Base\Services\Storage\DriverResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessFileDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $storageFileId,
        public readonly string $path,       // path ของไฟล์ใน disk
        public readonly string $diskName,   // disk หลัก เช่น 'minio'
        public readonly StorageDriverInterface $filedriver,
        public readonly ?string $userId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(
        DriverResolverService $driverResolver,
    ): void {
        Log::info('ProcessFileDeleteJob: started', [
            'id' => $this->storageFileId,
            'disk' => $this->diskName,
            'path' => $this->path,
        ]);

        try {
            // 1. ลบไฟล์จาก primary disk ผ่าน driver (MinioAdapter)
            $yn = $this->filedriver->delete($this->path);
            if ($yn) {
                Log::info('ProcessFileDeleteJob: primary disk delete', [
                    'disk' => $this->diskName,
                    'path' => $this->path,
                    'deleted' => $yn,
                ]);
            } else {
                Log::warning('ProcessFileDeleteJob: file not found on primary disk', [
                    'disk' => $this->diskName,
                    'path' => $this->path,
                ]);
            }

            // 2. ลบไฟล์จาก backup disk (best-effort)
            $backupPath = 'uploads/'.$this->path;
            try {
                if (Storage::disk('backuplocal')->exists($backupPath)) {
                    Storage::disk('backuplocal')->delete($backupPath);
                    Log::info('ProcessFileDeleteJob: backup deleted', ['path' => $backupPath]);
                }
            } catch (Throwable $e) {
                Log::warning('ProcessFileDeleteJob: backup delete failed', [
                    'path' => $backupPath,
                    'error' => $e->getMessage(),
                ]);
            }

            // 3. ลบไฟล์จาก localtmp (temp file จาก upload)
            $tmpPath = 'uploads/'.$this->path;
            try {
                if (Storage::disk('localtmp')->exists($tmpPath)) {
                    Storage::disk('localtmp')->delete($tmpPath);
                    Log::info('ProcessFileDeleteJob: tmp deleted', ['path' => $tmpPath]);
                }
            } catch (Throwable $e) {
                Log::warning('ProcessFileDeleteJob: tmp delete failed', [
                    'path' => $tmpPath,
                    'error' => $e->getMessage(),
                ]);
            }

            event(new FileDeleteCompleted(
                fileId: $this->storageFileId,
                path: $this->path,
                diskName: $this->diskName,
                userId: $this->userId,
            ));

            Log::info('ProcessFileDeleteJob: completed', [
                'id' => $this->storageFileId,
                'disk' => $this->diskName,
                'path' => $this->path,
            ]);
        } catch (Throwable $e) {
            Log::error('ProcessFileDeleteJob: failed', [
                'id' => $this->storageFileId,
                'disk' => $this->diskName,
                'path' => $this->path,
                'error' => $e->getMessage(),
            ]);

            throw $e; // ให้ queue retry ตาม $tries
        }
    }

    /**
     * เรียกโดย Laravel เมื่อ Job ล้มเหลวครบ $tries แล้ว (permanent failure)
     *
     * ⚠️ ไฟล์ยังคงอยู่ใน storage — ต้องจัดการ manually หรือผ่าน admin tool
     * Log ข้อมูลไว้เพื่อให้ทีม ops รู้ว่ามีไฟล์ค้างที่ต้องลบ
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessFileDeleteJob: permanently failed after all retries', [
            'storageFileId' => $this->storageFileId,
            'disk' => $this->diskName,
            'path' => $this->path,
            'error' => $exception->getMessage(),
            'action_needed' => 'Manual cleanup required — file may still exist on storage',
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return $this->storageFileId;
    }
}
