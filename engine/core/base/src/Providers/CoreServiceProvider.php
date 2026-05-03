<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Console\Commands\MinioHealthCheckCommand;
// ── Support ────────────────────────────────────────────────────────────
use Core\Base\Http\Middleware\VerifyCrossHostSignature;
use Core\Base\Services\Ai\AiCacheService;
use Core\Base\Services\Ai\Contracts\AiCacheServiceInterface;
use Core\Base\Services\Core\CommonService;
use Core\Base\Support\Contracts\ActionInterface;
// ── AI Services ────────────────────────────────────────────────────────
use Core\Base\Support\Contracts\FilterInterface;
use Core\Base\Support\Helpers\App\AppContext;
// ── Other Services ─────────────────────────────────────────────────────
use Core\Base\Support\Helpers\Cms\Action;
use Core\Base\Support\Helpers\Cms\Filter;
use Core\Base\Support\Helpers\Module\Contracts\ModuleHelperInterface;
use Core\Base\Support\Helpers\Module\ModuleHelper;
use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider as CBaseServiceProvider;

/**
 * CoreServiceProvider — Provider หลักสำหรับ Core Utilities
 *
 * ═══════════════════════════════════════════════════════════════
 *  ลงทะเบียน Base Services ใน IoC Container:
 * ═══════════════════════════════════════════════════════════════
 *  - Core Services      (CommonService)
 *  - Module Services    (ModuleHelper)
 *  - Support Services   (Action, Filter, AppContext)
 */
class CoreServiceProvider extends CBaseServiceProvider
{
    use LoadAndPublishDataTrait;

    protected const string PACKAGE_NAME = 'ppp-core';

    protected array $commands = [
        MinioHealthCheckCommand::class,
    ];

    public function register(): void
    {
        $this->setNamespace('Core\Base');
        $this->registerClassSingletons();
        $this->registerInterfaceBindings();
        $this->registerTransientBindings();
    }

    // ─── Boot ──────────────────────────────────────────────────

    public function boot(): void
    {
        $this->loadAndPublishConfigurations(['myapp', 'sodium', 'rsa', 'jwt', 'security', 'permissions', 'general', 'crypto', 'network']);
        $this->loadConstants(['constants']);
        $this->loadHelpers(['Common', 'App', 'Support', 'Lab', 'ActionFilter']);
        $this->loadAndPublishTranslations();
        $this->loadAndPublishTranslationsJson();
        $this->loadCommandsAndSchedules($this->commands);
        $this->registerMacros();
        $this->registerMorphMap();

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('crosshost.verify', VerifyCrossHostSignature::class);

        $this->registerAuditListeners();
    }

    /**
     * ลงทะเบียน Listeners สำหรับระบบ Audit Trail อัตโนมัติ
     */
    protected function registerAuditListeners(): void
    {
        /** @var \Core\Base\Support\Helpers\Cms\Action $action */
        $action = $this->app->make('core.action');

        // ตรวจจับเหตุการณ์ความปลอดภัยพื้นฐาน
        // ใช้ app()->make() แทน app('alias') เพื่อหลีกเลี่ยง Larastan container-resolver loop
        $action->addListener('auth.login.failed', function (array $data): void {
            /** @var object $audit */
            $audit = app()->make('core.audit');
            $audit->logSecurity('login_failed', $data);  // @phpstan-ignore method.notFound
        }, 100);

        $action->addListener('auth.unauthorized', function (array $data): void {
            /** @var object $audit */
            $audit = app()->make('core.audit');
            $audit->logSecurity('unauthorized_access', $data);  // @phpstan-ignore method.notFound
        }, 100);

        // ตัวอย่างการทำ Audit สำหรับเหตุการณ์ทั่วไป (อัปเดตให้รองรับ DTO)
        $action->addListener('data.sensitive.access', function (\Core\Base\Support\DTOs\SensitiveAccessDTO $dto): void {
            /** @var object $audit */
            $audit = app()->make('core.audit');
            $audit->log('sensitive_data_accessed', $dto->actionName, $dto->toArray());  // @phpstan-ignore method.notFound
        }, 100);
    }

    // ─── Morph Map ──────────────────────────────────────────────

    protected function registerMorphMap(): void
    {
        /** @var array<string, class-string<\Illuminate\Database\Eloquent\Model>> $morphMap */
        $morphMap = (array) config('core.base::general.morph_map', []);

        if (empty($morphMap)) {
            return;
        }

        Relation::enforceMorphMap($morphMap);
    }

    // ─── Response Macros ────────────────────────────────────────

    protected function registerMacros(): void
    {
        $this->registerJsonThMacro();
        $this->registerApiSuccessResponseMacro();
        $this->registerApiErrorResponseMacro();
    }

    protected function registerJsonThMacro(): void
    {
        Response::macro('jsonTh', function (mixed $data, int $status = 200, array $headers = [], int $options = 0): JsonResponse {
            return Response::json($data, $status, $headers, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });
    }

    protected function registerApiSuccessResponseMacro(): void
    {
        // คำนวณ baseOptions ครั้งเดียวตอน register — ไม่ต้องเรียก app()->environment() ทุก response
        $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->app->environment('local')) {
            $baseOptions |= JSON_PRETTY_PRINT;
        }

        Response::macro('apiSuccessResponse', function (mixed $data, ?string $message = null, int $code = 200, array $headers = [], int $options = 0) use ($baseOptions): JsonResponse {
            return Response::json(return_success($message ?? 'Operation successful', $data, $code), $code, $headers, $options | $baseOptions);
        });
    }

    protected function registerApiErrorResponseMacro(): void
    {
        // คำนวณ baseOptions ครั้งเดียวตอน register — ไม่ต้องเรียก app()->environment() ทุก response
        $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->app->environment('local')) {
            $baseOptions |= JSON_PRETTY_PRINT;
        }

        Response::macro('apiErrorResponse', function (?string $message = null, int $code = 400, mixed $data = null, array $headers = [], int $options = 0) use ($baseOptions): JsonResponse {
            return Response::json(return_error($message ?? 'An error occurred', $data, $code), $code, $headers, $options | $baseOptions);
        });
    }

    // ─── Singletons ────────────────────────────────────────────

    private function registerClassSingletons(): void
    {
        // ── Core ──────────────────────────────────────────────────
        $this->app->singleton(CommonService::class);
        $this->app->alias(CommonService::class, 'core.base.common');

        // ── Module ────────────────────────────────────────────────
        $this->app->singleton(ModuleHelper::class);
        $this->app->alias(ModuleHelper::class, 'core.module');

        // ── Support ───────────────────────────────────────────────
        $this->app->singleton(Action::class);
        $this->app->alias(Action::class, 'core.action');

        $this->app->singleton(Filter::class);
        $this->app->alias(Filter::class, 'core.filter');

        // ── AI ────────────────────────────────────────────────────
        $this->app->singleton(AiCacheService::class);
        $this->app->alias(AiCacheService::class, 'core.ai.cache');
    }

    // ─── Interface Bindings ────────────────────────────────────

    private function registerInterfaceBindings(): void
    {
        // Module
        $this->app->alias(ModuleHelper::class, ModuleHelperInterface::class);

        // Support
        $this->app->alias(Action::class, ActionInterface::class);
        $this->app->alias(Filter::class, FilterInterface::class);

        // AI
        $this->app->alias(AiCacheService::class, AiCacheServiceInterface::class);
    }

    // ─── Transient Bindings ────────────────────────────────────

    private function registerTransientBindings(): void
    {
        $this->app->bind(AppContext::class);
    }
}
