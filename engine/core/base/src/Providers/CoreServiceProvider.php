<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Console\Commands\MinioHealthCheckCommand;
use Core\Base\Services\Core\CommonService;
use Core\Base\Services\Crypto\HmacService;
use Core\Base\Services\Crypto\HybridEncryptionService;
use Core\Base\Support\Helpers\Crypto\EncryptionHelper;
use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\PasswordHasher;
use Core\Base\Services\Crypto\Contracts\TokenBlacklistServiceInterface;
use Core\Base\Support\Helpers\Cache\CacheManager;
use Core\Base\Support\Helpers\Cache\Contracts\CacheManagerInterface;
use Core\Base\Support\Helpers\Logs\AppLogger;
use Core\Base\Support\Helpers\Logs\Contracts\AppLoggerInterface;
use Core\Base\Support\Helpers\Module\ModuleHelper;
use Core\Base\Support\Helpers\Module\Contracts\ModuleHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\EncryptionHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\JwtHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\PasswordHasherInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\RsaHelperInterface;
use Core\Base\Support\Helpers\Crypto\RsaHelpers;
use Core\Base\Services\Crypto\TokenBlacklistService;
use Core\Base\Services\Imgproxy\Contracts\ImgproxyServiceInterface;
use Core\Base\Services\Imgproxy\ImgproxyService;
use Core\Base\Services\Session\Contracts\DeviceFingerprintServiceInterface;
use Core\Base\Services\Session\DeviceFingerprintService;
use Core\Base\Support\Action;
use Core\Base\Support\Contracts\ActionInterface;
use Core\Base\Support\Contracts\FilterInterface;
use Core\Base\Support\Filter;
use Core\Base\Support\Helpers\App\AppContext;
use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider as CBaseServiceProvider;

/**
 * CoreServiceProvider — Provider หลักของ Core\Base package
 *
 * ความรับผิดชอบ:
 *  - ลงทะเบียน singletons / bindings เข้า IoC container
 *  - Bootstrap: configurations, constants, helpers, translations, macros, morph map
 *  - ลงทะเบียน console commands
 *  - โหลด RepositoryServiceProvider
 *
 * หลักการออกแบบ:
 *  - Singleton ลงทะเบียนด้วย class เป็น canonical key เสมอ
 *    แล้ว alias string key ทับ → ทุก resolve path ได้ instance เดียวกัน
 *  - Interface binding ใช้ alias แทน singleton ซ้ำ เพื่อหลีกเลี่ยง dual-instantiation
 *  - register() ใช้ binding เท่านั้น — ห้ามอ่าน config() / env() ที่นี่
 *  - boot()   ใช้ bootstrap logic — รันหลัง register() ทุก provider เสร็จแล้ว
 *
 * วิธีเพิ่ม singleton ใหม่:
 *  - มี interface → เพิ่มใน registerInterfaceBindings()
 *  - ไม่มี interface → เพิ่มใน registerClassSingletons()
 *
 * @see RepositoryServiceProvider  สำหรับ Repository bindings
 * @see LoadAndPublishDataTrait     สำหรับ path resolution และ publishing
 */
class CoreServiceProvider extends CBaseServiceProvider
{
    use LoadAndPublishDataTrait;

    /**
     * ชื่อ package — ใช้เป็น prefix สำหรับ vendor:publish tags
     */
    protected const PACKAGE_NAME = 'ppp-core';

    /**
     * Console commands ที่ลงทะเบียนเมื่อรันใน console context
     *
     * @var array<int, class-string>
     */
    protected array $commands = [
        MinioHealthCheckCommand::class,
    ];

    // ─────────────────────────────────────────────────────────────────
    //  register()
    // ─────────────────────────────────────────────────────────────────

    /**
     * ลงทะเบียน services เข้า IoC container
     *
     * register() รันก่อน boot() เสมอ — ใช้เฉพาะ binding เท่านั้น
     * ห้าม resolve service ใดๆ ใน method นี้ (เช่น config(), app('...'))
     *
     * @return void
     */
    public function register(): void
    {
        $this->setNamespace('Core\Base');

        $this->registerClassSingletons();
        $this->registerInterfaceBindings();
        $this->registerTransientBindings();

        $this->app->register(RepositoryServiceProvider::class);
    }

    /**
     * Singleton services ที่ไม่มี Interface contract
     *
     * กลยุทธ์: ลงทะเบียน class เป็น canonical key ก่อน
     *          แล้ว alias string key ทับ → ทุก resolve path ได้ instance เดียวกัน
     *
     *  app(JwtHelper::class)   === app('core.crypto.jwt')   → true  ✓
     *
     * @return void
     */
    private function registerClassSingletons(): void
    {
        // ── Core Services ──────────────────────────────────────────────
        $this->app->singleton(CommonService::class);
        $this->app->alias(CommonService::class, 'core.base.common');

        // ── Crypto Helpers ───────────────────────────────────────────────
        $this->app->singleton(HashHelper::class);
        $this->app->alias(HashHelper::class, 'core.crypto.hash');

        $this->app->singleton(EncryptionHelper::class);
        $this->app->alias(EncryptionHelper::class, 'core.crypto.crypt');

        $this->app->singleton(PasswordHasher::class);
        $this->app->alias(PasswordHasher::class, 'core.crypto.password');

        $this->app->singleton(JwtHelper::class);
        $this->app->alias(JwtHelper::class, 'core.crypto.jwt');

        $this->app->singleton(HmacService::class, function ($app) {
            return new HmacService(
                $app->make(HashHelper::class),
                $app->make(PasswordHasher::class),
            );
        });
        $this->app->alias(HmacService::class, 'core.crypto.hmac');

        $this->app->singleton(HybridEncryptionService::class, function ($app) {
            return new HybridEncryptionService(
                $app->make(RsaHelpers::class),
                $app->make(EncryptionHelper::class),
            );
        });
        $this->app->alias(HybridEncryptionService::class, 'core.crypto.hybrid');

        $this->app->singleton(TokenBlacklistService::class);
        $this->app->alias(TokenBlacklistService::class, 'core.crypto.blacklist');

        // ── Cache ─────────────────────────────────────────────────────
        $this->app->singleton(CacheManager::class);
        $this->app->alias(CacheManager::class, 'core.cache');

        // ── Logger ────────────────────────────────────────────────────
        $this->app->singleton(AppLogger::class);
        $this->app->alias(AppLogger::class, 'core.logger');

        // ── Module ────────────────────────────────────────────────────
        $this->app->singleton(ModuleHelper::class);
        $this->app->alias(ModuleHelper::class, 'core.module');

        // ── Support ────────────────────────────────────────────────────
        $this->app->singleton(Action::class);
        $this->app->alias(Action::class, 'core.action');

        $this->app->singleton(Filter::class);
        $this->app->alias(Filter::class, 'core.filter');
        $this->app->alias(Action::class, ActionInterface::class);
        $this->app->alias(Filter::class, FilterInterface::class);

        // bind() แทน singleton() เพราะ class มี mutable state ต่อ request
        // singleton จะทำให้ parsed state รั่วข้าม request ใน Octane / queue worker
        $this->app->bind(DeviceFingerprintService::class);
        $this->app->alias(DeviceFingerprintService::class, 'core.session.device_fingerprint');
        $this->app->alias(DeviceFingerprintService::class, DeviceFingerprintServiceInterface::class);
    }

    /**
     * Singleton services ที่มี Interface contract
     *
     * กลยุทธ์: ลงทะเบียน Interface → Implementation เป็น canonical singleton
     *          แล้ว alias string key ทับ — ไม่ singleton ซ้ำ เพื่อหลีกเลี่ยง dual-instantiation
     *
     *  app(RsaHelperInterface::class) === app('core.crypto.rsa') → true  ✓
     *
     * @return void
     */
    private function registerInterfaceBindings(): void
    {
        // ── Crypto ─────────────────────────────────────────────────────
        $this->app->alias(HashHelper::class, HashHelperInterface::class);
        $this->app->alias(EncryptionHelper::class, EncryptionHelperInterface::class);
        $this->app->alias(PasswordHasher::class, PasswordHasherInterface::class);
        $this->app->alias(JwtHelper::class, JwtHelperInterface::class);
        $this->app->alias(TokenBlacklistService::class, TokenBlacklistServiceInterface::class);
        $this->app->alias(CacheManager::class, CacheManagerInterface::class);
        $this->app->alias(AppLogger::class, AppLoggerInterface::class);
        $this->app->alias(ModuleHelper::class, ModuleHelperInterface::class);

        $this->app->singleton(RsaHelpers::class);
        $this->app->alias(RsaHelpers::class, RsaHelperInterface::class);
        $this->app->alias(RsaHelpers::class, 'core.crypto.rsa');

        // ── Imgproxy ───────────────────────────────────────────────────
        $this->app->singleton(ImgproxyServiceInterface::class, ImgproxyService::class);
        $this->app->alias(ImgproxyServiceInterface::class, 'core.imgproxy');
    }

    /**
     * Transient (per-request) bindings — สร้าง instance ใหม่ทุกครั้งที่ resolve
     *
     * ใช้กับ value object หรือ context object ที่ไม่ควร share state ข้าม request
     *
     * @return void
     */
    private function registerTransientBindings(): void
    {
        $this->app->bind(AppContext::class);
    }

    // ─────────────────────────────────────────────────────────────────
    //  boot()
    // ─────────────────────────────────────────────────────────────────

    /**
     * Bootstrap services
     *
     * ลำดับสำคัญ (อย่าสลับ):
     *  1. Configurations — ต้องโหลดก่อน เพราะ step อื่นอาจอ่าน config()
     *  2. Constants, Helpers — ก่อน morph map เพราะ model class ต้องพร้อม
     *  3. Translations — PHP + JSON
     *  4. Commands — ลงทะเบียนเฉพาะ console context
     *  5. Macros — ต้องการ Response facade พร้อม
     *  6. MorphMap — หลังสุด ต้องการ config + model class พร้อมทั้งคู่
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadAndPublishConfigurations(['myapp', 'permissions', 'general', 'crypto']);
        $this->loadConstants(['constants']);
        $this->loadHelpers(['Common', 'App', 'Support', 'lab']);
        $this->loadAndPublishTranslations();
        $this->loadAndPublishTranslationsjson();
        $this->loadCommandsAndSchedules($this->commands);
        $this->registerMacros();
        $this->registerMorphMap();
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้จัดการ
     *
     * ใช้สำหรับ deferred loading documentation และการ introspect container
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            // Class keys
            AppLogger::class,
            AppLoggerInterface::class,
            ModuleHelper::class,
            ModuleHelperInterface::class,
            CacheManager::class,
            CacheManagerInterface::class,
            CommonService::class,
            HashHelper::class,
            EncryptionHelper::class,
            PasswordHasher::class,
            JwtHelper::class,
            TokenBlacklistService::class,
            HmacService::class,
            HybridEncryptionService::class,
            RsaHelpers::class,
            HashHelperInterface::class,
            EncryptionHelperInterface::class,
            PasswordHasherInterface::class,
            JwtHelperInterface::class,
            TokenBlacklistServiceInterface::class,
            RsaHelperInterface::class,
            ImgproxyServiceInterface::class,
            Action::class,
            ActionInterface::class,
            Filter::class,
            FilterInterface::class,
            DeviceFingerprintService::class,
            DeviceFingerprintServiceInterface::class,
            AppContext::class,
            // String alias keys
            'core.logger',
            'core.module',
            'core.cache',
            'core.base.common',
            'core.crypto.hash',
            'core.crypto.crypt',
            'core.crypto.password',
            'core.crypto.jwt',
            'core.crypto.blacklist',
            'core.crypto.hmac',
            'core.crypto.hybrid',
            'core.crypto.rsa',
            'core.imgproxy',
            'core.action',
            'core.filter',
            'core.session.device_fingerprint',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Morph Map
    // ─────────────────────────────────────────────────────────────────

    /**
     * ลงทะเบียน morph map สำหรับ polymorphic relations
     *
     * อ่านจาก config('core.base.general.morph_map') — ไม่ hardcode ใน provider
     *
     * เหตุผล:
     *  - หลีกเลี่ยง hardcoded model class ใน core provider
     *  - Module อื่นสามารถ merge morph_map ของตัวเองเข้า config ได้โดยไม่ต้อง
     *    แก้ provider นี้ (Open/Closed Principle)
     *  - enforceMorphMap() บังคับให้ทุก morph type ต้องประกาศ — ป้องกัน
     *    class name รั่วในฐานข้อมูล
     *
     * หากต้องการเพิ่ม morph type:
     *  แก้ที่ engine/core/base/config/general.php ใน key 'morph_map'
     *
     * @return void
     */
    protected function registerMorphMap(): void
    {
        $morphMap = (array) config('core.base.general.morph_map', []);

        if (empty($morphMap)) {
            return;
        }

        Relation::enforceMorphMap($morphMap);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Response Macros
    // ─────────────────────────────────────────────────────────────────

    /**
     * ลงทะเบียน Response macros ทั้งหมด
     *
     * @return void
     */
    protected function registerMacros(): void
    {
        $this->registerJsonThMacro();
        $this->registerApiSuccessResponseMacro();
        $this->registerApiErrorResponseMacro();
    }

    /**
     * jsonTh macro — JSON response พร้อม UNESCAPED_UNICODE สำหรับภาษาไทย
     *
     * Usage: Response::jsonTh($data, 200)
     *
     * @return void
     */
    protected function registerJsonThMacro(): void
    {
        Response::macro('jsonTh', function (
            mixed $data,
            int $status = 200,
            array $headers = [],
            int $options = 0,
        ): JsonResponse {
            return Response::json(
                $data,
                $status,
                $headers,
                $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        });
    }

    /**
     * apiSuccessResponse macro — response format มาตรฐานสำหรับ success
     *
     * เพิ่ม JSON_PRETTY_PRINT อัตโนมัติใน local environment
     *
     * Usage: Response::apiSuccessResponse($data, 'สำเร็จ', 200)
     *
     * @return void
     */
    protected function registerApiSuccessResponseMacro(): void
    {
        Response::macro('apiSuccessResponse', function (
            mixed $data,
            ?string $message = null,
            int $status = 200,
            array $headers = [],
            int $options = 0,
        ): JsonResponse {
            $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

            if (app()->environment('local')) {
                $baseOptions |= JSON_PRETTY_PRINT;
            }

            return Response::json(
                presponsesuccess($message ?? 'Operation successful', $data),
                $status,
                $headers,
                $options | $baseOptions,
            );
        });
    }

    /**
     * apiErrorResponse macro — response format มาตรฐานสำหรับ error
     *
     * เพิ่ม JSON_PRETTY_PRINT อัตโนมัติใน local environment
     *
     * Usage: Response::apiErrorResponse('ข้อผิดพลาด', 422, $errors)
     *
     * @return void
     */
    protected function registerApiErrorResponseMacro(): void
    {
        Response::macro('apiErrorResponse', function (
            ?string $message = null,
            int $status = 400,
            mixed $data = null,
            array $headers = [],
            int $options = 0,
        ): JsonResponse {
            $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

            if (app()->environment('local')) {
                $baseOptions |= JSON_PRETTY_PRINT;
            }

            return Response::json(
                presponseerror($message ?? 'An error occurred', $data),
                $status,
                $headers,
                $options | $baseOptions,
            );
        });
    }
}
