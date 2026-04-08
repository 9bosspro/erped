<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Security\Contracts\EncryptionServiceInterface;
use Core\Base\Services\Security\Contracts\SodiumHybridP2pInterface;
use Core\Base\Services\Security\Contracts\TokenBlacklistServiceInterface as SecurityTokenBlacklistInterface;
// ── Crypto Helpers ────────────────────────────────────────────────────
use Core\Base\Services\Security\EncryptionService;
use Core\Base\Services\Security\SodiumHybridP2p;
use Core\Base\Services\Security\TokenBlacklistService as SecurityTokenBlacklistService;
use Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\JwtHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\PasswordHasherInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\SodiumHelperInterface;
use Core\Base\Support\Helpers\Crypto\HashHelper;
// ── Security Services ─────────────────────────────────────────────────
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\PasswordHasher;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * SecurityServiceProvider — ลงทะเบียน Service สำหรับ Security และ Crypto
 *
 * ลงทะเบียน:
 * - Crypto Helpers  (Hash, Password, JWT, Sodium)
 * - Security Services (Encryption, SodiumHybridP2p, TokenBlacklist)
 *
 * ใช้ DeferrableProvider เพื่อโหลด services เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class SecurityServiceProvider extends ServiceProvider implements DeferrableProvider
{
    //  use LoadAndPublishDataTrait;
    protected const PACKAGE_NAME = 'ppp-base-security';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        // $this->setNamespace('Core\Base');
        // ── Security Services ─────────────────────────────────────
        $this->app->singleton(EncryptionService::class);
        $this->app->alias(EncryptionService::class, 'core.security.encryption');
        $this->app->alias(EncryptionService::class, EncryptionServiceInterface::class);
        // ── Crypto Helpers ────────────────────────────────────────
        $this->app->singleton(HashHelper::class);
        $this->app->alias(HashHelper::class, 'core.crypto.hash');
        $this->app->singleton(PasswordHasher::class);
        $this->app->alias(PasswordHasher::class, 'core.crypto.password');
        $this->app->singleton(JwtHelper::class);
        $this->app->alias(JwtHelper::class, 'core.crypto.jwt');
        $this->app->singleton(SodiumHelper::class, function () {
            $keyb64 = (string) config('core.base::security.base64key32', '');
            if ($keyb64 === '') {
                throw new RuntimeException('Security key [core.base::security.base64key32] is not configured.');
            }
            //   $key32 = $this->decodeb64($keyb64);

            return new SodiumHelper($keyb64);
        });
        $this->app->alias(SodiumHelper::class, 'core.crypto.sodium');
        //
        // Crypto Helpers
        $this->app->alias(HashHelper::class, HashHelperInterface::class);
        $this->app->alias(PasswordHasher::class, PasswordHasherInterface::class);
        $this->app->alias(JwtHelper::class, JwtHelperInterface::class);
        $this->app->alias(SodiumHelper::class, SodiumHelperInterface::class);
        //

        $this->app->singleton(SodiumHybridP2p::class, function ($app) {
            $key = (string) config('core.base::security.key64', '');
            if ($key === '') {
                throw new RuntimeException('Security key [core.base::security.key64] is not configured.');
            }

            return new SodiumHybridP2p($key);
        });
        $this->app->alias(SodiumHybridP2p::class, 'core.security.hybrid_p2p');

        $this->app->singleton(SecurityTokenBlacklistService::class, function ($app) {
            return new SecurityTokenBlacklistService(
                $app->make(JwtHelper::class),
            );
        });
        $this->app->alias(SecurityTokenBlacklistService::class, 'core.security.blacklist');
        //
        // Security Services

        $this->app->alias(SodiumHybridP2p::class, SodiumHybridP2pInterface::class);
        $this->app->alias(SecurityTokenBlacklistService::class, SecurityTokenBlacklistInterface::class);
        // $this->app->singleton(CacheService::class);
        // /  $this->registerInterfaceBindings();
        // $this->app->alias(CacheService::class, 'core.base.cache');
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            EncryptionServiceInterface::class,
            // Security Services
            EncryptionService::class,
            'core.security.encryption',
            // Crypto Helpers
            HashHelper::class,
            'core.crypto.hash',
            PasswordHasher::class,
            'core.crypto.password',
            JwtHelper::class,
            'core.crypto.jwt',
            SodiumHelper::class,
            'core.crypto.sodium',
            HashHelperInterface::class,
            PasswordHasherInterface::class,
            JwtHelperInterface::class,
            SodiumHelperInterface::class,
            SodiumHybridP2p::class,
            'core.security.hybrid_p2p',
            SecurityTokenBlacklistService::class,
            'core.security.blacklist',

            SodiumHybridP2pInterface::class,
            SecurityTokenBlacklistInterface::class,
        ];
    }
}
