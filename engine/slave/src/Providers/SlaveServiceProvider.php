<?php

declare(strict_types=1);

namespace Slave\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Http\Middleware\ForceTheme;
//use Slave\Http\Middleware\SecurityHeaders;
use Slave\Services\Master\MasterClientService;

/**
 * SlaveServiceProvider — bootstrap สำหรับ EvoEngine Slave Client
 *
 * ทำหน้าที่เป็น thin orchestrator เท่านั้น — ไม่มี business logic
 */
class SlaveServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected const string PACKAGE_NAME = 'ampol-slave';

    /**
     * ลงทะเบียน services เข้า DI container
     */
    public function register(): void
    {
        $this->setNamespace('Slave');
        $this->registerMasterClient();
    }

    /**
     * Bootstrap services หลัง register เสร็จสิ้น
     */
    public function boot(): void
    {
        $this->loadConstants(['constants']);
        $this->loadAndPublishConfigurations(['client', 'security']);
        $this->loadHelpers(['Slave']);
        $this->loadRoutes(['api']);
        //  $this->registerMiddlewareAliases();
    }

    /**
     * ลงทะเบียน MasterClientService เป็น singleton ผ่าน interface
     */
    private function registerMasterClient(): void
    {
        $this->app->singleton(MasterClientInterface::class, static function ($app): MasterClientService {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app['config'];

            $masterUrl    = $config->get('slave::client.master_url', '');
            $clientId     = $config->get('slave::client.client_id', '');
            $clientSecret = $config->get('slave::client.client_secret', '');

            return new MasterClientService(
                masterUrl: \is_string($masterUrl) ? $masterUrl : '',
                clientId: \is_string($clientId) ? $clientId : '',
                clientSecret: \is_string($clientSecret) ? $clientSecret : '',
            );
        });

        $this->app->alias(MasterClientInterface::class, 'slave.master');
    }

    /**
     * ลงทะเบียน middleware aliases
     */
    private function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        //  $router = $this->app->make(Router::class);
        //   $router->aliasMiddleware('slave.security', SecurityHeaders::class);
        //  $router->aliasMiddleware('slave.theme', ForceTheme::class);
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            MasterClientInterface::class,
            'slave.master',
        ];
    }
}
