<?php

declare(strict_types=1);

namespace Slave\Providers;

use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Http\Middleware\ForceTheme;
// use Slave\Http\Middleware\SecurityHeaders;
use Slave\Services\BackendApi\BackendApiClient;
use Slave\Services\Master\MasterClientService;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Slave\Listeners\ClearMasterTokensOnLogout;


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
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->singleton(BackendApiClient::class);
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
        $this->registerBladeDirectives();
        $this->registerEventListeners();
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

    /**
     * ลงทะเบียน Blade directives ของ Slave
     *
     * @nonce — ใส่ attribute nonce="..." ใน inline script/style ให้อัตโนมัติ
     *          ใช้ภายใน Blade เท่านั้น เช่น  <script @nonce>...</script>
     */
    private function registerBladeDirectives(): void
    {
        Blade::directive('nonce', static fn(): string => "<?php echo 'nonce=\"' . e(csp_nonce()) . '\"'; ?>");
    }

    /**
     * ลงทะเบียน MasterClientService เป็น singleton ผ่าน interface
     */
    private function registerMasterClient(): void
    {
        $this->app->singleton(MasterClientInterface::class, static function ($app): MasterClientService {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app['config'];

            $masterUrl = $config->get('slave::client.master_url', '');
            $clientId = $config->get('slave::client.client_id', '');
            $clientSecret = $config->get('slave::client.client_secret', '');
            $defaultScope = $config->get('slave::client.default_scope', '');

            $masterUrl = \is_string($masterUrl) ? $masterUrl : '';
            $clientId = \is_string($clientId) ? $clientId : '';
            $clientSecret = \is_string($clientSecret) ? $clientSecret : '';

            /** @var SodiumHelper $sodium */
            $sodium = $app->make('core.crypto.sodium');

            // 1. สร้าง TokenManager เพื่อจัดการเรื่องความปลอดภัยและ Token Lifecycle
            $tokenManager = new \Slave\Services\Master\TokenManager(
                masterUrl: $masterUrl,
                clientId: $clientId,
                clientSecret: $clientSecret,
                sodium: $sodium,
                signatureSeed: (string) $config->get('slave::client.signature_seed', ''),
                publicBox: (string) $config->get('slave::client.public_box', ''),
                tokenStoreName: $config->get('slave::client.token_store'), // 💡 ดึงค่าเริ่มต้นจาก config อัตโนมัติ
            );

            // 2. ส่งเข้า MasterClientService (Dependency Injection)
            return new MasterClientService(
                masterUrl: $masterUrl,
                clientId: $clientId,
                clientSecret: $clientSecret,
                tokenManager: $tokenManager,
                defaultScope: \is_string($defaultScope) ? $defaultScope : '',
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
     * ลงทะเบียน Event Listeners ของ Slave Package
     */
    private function registerEventListeners(): void
    {
        // 🚨 ดักจับเหตุการณ์ Logout และสั่งล้าง Master Tokens ทันที
        Event::listen(
            Logout::class,
            ClearMasterTokensOnLogout::class
        );
    }
}
