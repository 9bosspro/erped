<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Console\Commands\MinioHealthCheckCommand;
// ── Support ────────────────────────────────────────────────────────────
use Core\Base\Http\Middleware\VerifyCrossHostSignature;
use Core\Base\Services\Core\CommonService;
use Core\Base\Services\Session\Contracts\DeviceFingerprintServiceInterface;
use Core\Base\Services\Session\DeviceFingerprintService;
use Core\Base\Support\Action;
use Core\Base\Support\Contracts\ActionInterface;
use Core\Base\Support\Contracts\FilterInterface;
// ── Other Services ─────────────────────────────────────────────────────
use Core\Base\Support\Filter;
use Core\Base\Support\Helpers\App\AppContext;
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

    protected const PACKAGE_NAME = 'ppp-core';

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
        $this->loadAndPublishConfigurations(['myapp', 'sodium', 'rsa', 'jwt', 'security', 'permissions', 'general', 'crypto']);
        $this->loadConstants(['constants']);
        $this->loadHelpers(['Common', 'App', 'Support', 'Lab', 'ActionFilter']);
        $this->loadAndPublishTranslations();
        $this->loadAndPublishTranslationsjson();
        $this->loadCommandsAndSchedules($this->commands);
        $this->registerMacros();
        $this->registerMorphMap();

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('crosshost.verify', VerifyCrossHostSignature::class);
    }

    // ─── Morph Map ──────────────────────────────────────────────

    protected function registerMorphMap(): void
    {
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
        Response::macro('apiSuccessResponse', function (mixed $data, ?string $message = null, int $code = 200, array $headers = [], int $options = 0): JsonResponse {
            $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            if (app()->environment('local')) {
                $baseOptions |= JSON_PRETTY_PRINT;
            }

            return Response::json(return_success($message ?? 'Operation successful', $data, $code), $code, $headers, $options | $baseOptions);
        });
    }

    protected function registerApiErrorResponseMacro(): void
    {
        Response::macro('apiErrorResponse', function (?string $message = null, int $code = 400, mixed $data = null, array $headers = [], int $options = 0): JsonResponse {
            $baseOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            if (app()->environment('local')) {
                $baseOptions |= JSON_PRETTY_PRINT;
            }

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
    }

    // ─── Interface Bindings ────────────────────────────────────

    private function registerInterfaceBindings(): void
    {
        // Module
        $this->app->alias(ModuleHelper::class, ModuleHelperInterface::class);

        // Support
        $this->app->alias(Action::class, ActionInterface::class);
        $this->app->alias(Filter::class, FilterInterface::class);

        // Session / Device
        $this->app->alias(DeviceFingerprintService::class, DeviceFingerprintServiceInterface::class);
    }

    // ─── Transient Bindings ────────────────────────────────────

    private function registerTransientBindings(): void
    {
        $this->app->bind(AppContext::class);
        $this->app->bind(DeviceFingerprintService::class);
        $this->app->alias(DeviceFingerprintService::class, 'core.session.device_fingerprint');
    }
}
