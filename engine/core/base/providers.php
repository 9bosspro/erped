<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modular Core Service Providers
    |--------------------------------------------------------------------------
    | รวมศูนย์รายชื่อ Provider ของระบบ Modular ไว้ที่นี่เพื่อความสะดวกในการจัดการ
    | และเพื่อให้ Laravel รองรับ Deferred Loading (Lazy Load) ได้สมบูรณ์แบบ
    */
    Core\Base\Providers\BaseServiceProvider::class,
    Core\Base\Providers\Service\DeviceFingerprintServiceProvider::class,
    Core\Base\Providers\Service\RateLimitingServiceProvider::class,
    Core\Base\Providers\Service\LogServiceProvider::class,
    Core\Base\Providers\RepositoryServiceProvider::class,
    Core\Base\Providers\Service\SecurityServiceProvider::class,
    Core\Base\Providers\Service\CacheServiceProvider::class,
    Core\Base\Providers\Service\StorageServiceProvider::class,
    Core\Base\Providers\Service\ImgproxyServiceProvider::class,
];
