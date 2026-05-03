<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Storage\Contracts\DriverResolverServiceInterface;
use Core\Base\Services\Storage\Contracts\FileStorageServiceInterface;
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
        $this->app->alias(FileStorageService::class, FileStorageServiceInterface::class);
        $this->app->alias(FileStorageService::class, 'core.storage.files');

        $this->app->singleton(DriverResolverService::class);
        $this->app->alias(DriverResolverService::class, DriverResolverServiceInterface::class);
        $this->app->alias(DriverResolverService::class, 'core.storage.driver');
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * ต้องระบุทั้ง concrete class และ alias ครบทุกรายการ
     * เพื่อให้ DeferrableProvider โหลด provider ถูกต้องทุกเส้นทางการ resolve
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            FileStorageService::class,
            FileStorageServiceInterface::class,
            'core.storage.files',
            DriverResolverService::class,
            DriverResolverServiceInterface::class,
            'core.storage.driver',
        ];
    }
}
