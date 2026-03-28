<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Contracts\Cache\CacheServiceInterface;
// Contracts
use Core\Base\Contracts\DataTables\DatatablesServiceInterface;
use Core\Base\Contracts\FileStorage\ChunkedUploadServiceInterface;
use Core\Base\Contracts\FileStorage\FileStorageServiceInterface;
use Core\Base\Contracts\FileStorage\StorageDriverInterface;
use Core\Base\Contracts\Health\StorageHealthCheckInterface;
use Core\Base\Contracts\Logging\LogParserServiceInterface;
use Core\Base\Contracts\Spreadsheet\SpreadsheetServiceInterface;
use Core\Base\Contracts\User\RegistrationServiceInterface;
use Core\Base\Services\CacheService;
// Services
use Core\Base\Services\DatatablesService;
use Core\Base\Services\LogParserService;
use Core\Base\Services\PhpSpreadsheetService;
use Core\Base\Services\Storage\ChunkedUploadService;
use Core\Base\Services\Storage\FileStorageService;
use Core\Base\Services\Storage\MinioHealthCheck;
use Core\Base\Services\User\RegistrationService;
use Core\Base\Support\Helpers\Array\ArrayTransformer;
// Helpers
use Core\Base\Support\Helpers\Array\ArrayValidator;
use Core\Base\Support\Helpers\Array\SetOperator;
use Core\Base\Support\Helpers\File\FileContentHandler;
use Core\Base\Support\Helpers\File\FileSystemOperator;
use Core\Base\Support\Helpers\File\ImageBase64Converter;
use Core\Base\Support\Helpers\File\MimeTypeResolver;
use Core\Base\Support\Helpers\File\PhpConfigManager;
use Core\Base\Support\Helpers\String\StringCleaner;
use Core\Base\Support\Helpers\String\StringExtractor;
use Core\Base\Support\Helpers\String\StringFormatter;
use Core\Base\Support\Helpers\String\ThaiTextProcessor;
use Illuminate\Support\ServiceProvider;

/**
 * LabCoreServicesProvider — ลงทะเบียน Services และ Helpers ทั่วไป
 *
 * ความรับผิดชอบ:
 *  - ลงทะเบียน Interface → Implementation bindings (Cache, DataTables, Storage, ฯลฯ)
 *  - ลงทะเบียน Helper singletons (PasswordHasher, MimeTypeResolver, ฯลฯ)
 *  - ลงทะเบียน string aliases สำหรับ resolve ผ่านชื่อย่อ
 *
 * หมายเหตุ:
 *  - Crypto Helpers หลัก (HashHelper, EncryptionHelper, JwtHelper, RsaHelpers)
 *    ลงทะเบียนใน CoreServiceProvider — ไม่ซ้ำซ้อนที่นี่
 *  - PasswordHasher ลงทะเบียนเป็น singleton เพราะ stateless + อ่าน config ครั้งเดียว
 */
class LabCoreServicesProvider extends ServiceProvider
{
    /**
     * Simple bindings (Interface => Implementation).
     */
    public array $bindings = [
        CacheServiceInterface::class => CacheService::class,
        DatatablesServiceInterface::class => DatatablesService::class,
        LogParserServiceInterface::class => LogParserService::class,
        SpreadsheetServiceInterface::class => PhpSpreadsheetService::class,
        StorageHealthCheckInterface::class => MinioHealthCheck::class,
    ];

    /**
     * Singleton bindings.
     */
    public array $singletons = [
        // Helper singletons (immutable, stateless)
        MimeTypeResolver::class => MimeTypeResolver::class,
        ArrayValidator::class => ArrayValidator::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //  dd('CoreServicesProvider register');
        $this->registerHelperBindings();
        $this->registerServiceBindings();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerAliases();
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            CacheServiceInterface::class,
            DatatablesServiceInterface::class,
            LogParserServiceInterface::class,
            SpreadsheetServiceInterface::class,
            FileStorageServiceInterface::class,
            ChunkedUploadServiceInterface::class,
            RegistrationServiceInterface::class,
            StorageHealthCheckInterface::class,
            // Helpers
            MimeTypeResolver::class,
            FileSystemOperator::class,
            FileContentHandler::class,
            PhpConfigManager::class,
            ImageBase64Converter::class,
            ThaiTextProcessor::class,
            StringExtractor::class,
            StringCleaner::class,
            StringFormatter::class,
            ArrayValidator::class,
            ArrayTransformer::class,
            SetOperator::class,
        ];
    }

    /**
     * Register helper class bindings.
     */
    protected function registerHelperBindings(): void
    {
        // File Helpers
        $this->app->bind(FileSystemOperator::class);
        $this->app->bind(FileContentHandler::class);
        $this->app->bind(PhpConfigManager::class);
        $this->app->bind(ImageBase64Converter::class);

        // Crypto Helpers (PasswordHasher registered in CoreServiceProvider with alias core.crypto.password)

        // String Helpers
        $this->app->bind(ThaiTextProcessor::class);
        $this->app->bind(StringExtractor::class);
        $this->app->bind(StringCleaner::class);
        $this->app->bind(StringFormatter::class);

        // Array Helpers
        $this->app->bind(ArrayTransformer::class);
        $this->app->bind(SetOperator::class, function ($app) {
            return new SetOperator($app->make(ArrayTransformer::class));
        });
    }

    /**
     * Register service bindings with dependencies.
     */
    protected function registerServiceBindings(): void
    {
        // FileStorageService with driver injection
        $this->app->singleton(FileStorageServiceInterface::class, function ($app) {
            $driver = $app->make(StorageDriverInterface::class);

            return new FileStorageService($driver);
        });

        // ChunkedUploadService
        $this->app->singleton(ChunkedUploadServiceInterface::class, function ($app) {
            return new ChunkedUploadService(
                config('filesystems.default', 'minio'),
            );
        });

        // RegistrationService with dependencies
        $this->app->bind(RegistrationServiceInterface::class, function ($app) {
            return new RegistrationService(
                $app->make(\Core\Base\Repositories\User\UserRepository::class),
                $app->make(\Core\Base\Repositories\Auth\RegisterTokenRepository::class),
                $app->make(\Core\Base\Support\Helpers\Crypto\JwtHelper::class),
            );
        });
    }

    /**
     * Register service aliases for convenience.
     */
    protected function registerAliases(): void
    {
        $this->app->alias(CacheServiceInterface::class, 'cache.service');
        $this->app->alias(FileStorageServiceInterface::class, 'file.storage');
        $this->app->alias(ChunkedUploadServiceInterface::class, 'chunked.upload');
        $this->app->alias(RegistrationServiceInterface::class, 'registration.service');

        // Helper aliases
        $this->app->alias(ThaiTextProcessor::class, 'thai.text');
    }
}
