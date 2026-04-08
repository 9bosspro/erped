<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Storage\DriverResolverService;
use Core\Base\Services\Storage\FileStorageService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * StorageServiceProvider — ลงทะเบียน Service สำหรับ File Storage
 *
 * ลงทะเบียนเฉพาะ Service layer ที่เป็น singleton:
 * - FileStorageService    — orchestrator หลักสำหรับ upload/delete/read
 * - DriverResolverService — resolve StorageDriverInterface ตาม disk name
 *
 * ใช้ DeferrableProvider เพื่อโหลด services เฉพาะเมื่อถูกร้องขอจริงๆ
 *
 * หมายเหตุ: Repository bindings (StorageFileInterface, StorageDiskInterface)
 * จัดการโดย RepositoryServiceProvider เพื่อป้องกัน binding ซ้ำ
 */
class StorageServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        $this->app->singleton(FileStorageService::class);
        $this->app->singleton(DriverResolverService::class);
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            FileStorageService::class,
            DriverResolverService::class,
        ];
    }
}
