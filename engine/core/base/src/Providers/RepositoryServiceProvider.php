<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Repositories\Auth\RegisterTokenInterface;
use Core\Base\Repositories\Auth\RegisterTokenRepository;
use Core\Base\Repositories\Files\Interfaces\StorageDiskInterface;
use Core\Base\Repositories\Files\Interfaces\StorageFileInterface;
use Core\Base\Repositories\Files\StorageDiskRepository;
use Core\Base\Repositories\Files\StorageFileRepository;
use Core\Base\Repositories\User\UserInterface;
use Core\Base\Repositories\User\UserRepository;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * RepositoryServiceProvider — ผูก Repository Interfaces กับ Implementations
 *
 * เหตุผลที่แยก Provider นี้ออกมาจาก CoreServiceProvider:
 *  - SRP: CoreServiceProvider มีหน้าที่ bootstrap — ไม่ควรยุ่งกับ binding
 *  - ทดสอบง่ายกว่า: swap implementation ใน test ได้โดย re-register provider เดียว
 *  - Scalable: เพิ่ม repository ใหม่ได้ที่ $bindings โดยไม่แตะ provider อื่น
 *
 * ────────────────────────────────────────────────────────────────────
 * วิธีเพิ่ม Repository ใหม่:
 *  เพิ่มคู่ Interface → Implementation ใน $bindings ด้านล่างเท่านั้น
 *  ไม่ต้อง override method ใดๆ
 * ────────────────────────────────────────────────────────────────────
 */
class RepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Interface → Implementation bindings
     *
     * ใช้ bind() (ไม่ใช่ singleton) เพราะ Repository เป็น stateless per request
     * ถ้า Repository ต้องการ singleton (เช่น cache in-memory) ให้ override ใน subclass
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserInterface::class => UserRepository::class,
        RegisterTokenInterface::class => RegisterTokenRepository::class,
        StorageFileInterface::class => StorageFileRepository::class,
        StorageDiskInterface::class => StorageDiskRepository::class,
    ];

    /**
     * ลงทะเบียน repository bindings ทั้งหมด
     */
    public function register(): void
    {
        foreach ($this->bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide
     * ใช้ใน deferred loading (ถ้าต้องการ)
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->bindings);
    }
}
