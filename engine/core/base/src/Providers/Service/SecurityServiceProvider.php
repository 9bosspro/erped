<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Security\Contracts\EncryptionServiceInterface;
use Core\Base\Services\Security\Contracts\SodiumHybridP2pInterface;
use Core\Base\Services\Security\Contracts\TokenBlacklistServiceInterface as SecurityTokenBlacklistInterface;
use Core\Base\Services\Security\EncryptionService;
use Core\Base\Services\Security\SodiumHybridP2p;
use Core\Base\Services\Security\TokenBlacklistService as SecurityTokenBlacklistService;
use Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\JwtHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\PasswordHasherInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\SodiumHelperInterface;
use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\PasswordHasher;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
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
    protected const string PACKAGE_NAME = 'ppp-base-security';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        // ── Security Services ─────────────────────────────────────
        $this->app->singleton(EncryptionService::class);
        $this->app->alias(EncryptionService::class, 'core.security.encryption');
        $this->app->alias(EncryptionService::class, EncryptionServiceInterface::class);

        // ── Crypto Helpers ────────────────────────────────────────
        $this->app->singleton(HashHelper::class);
        $this->app->alias(HashHelper::class, 'core.crypto.hash');
        $this->app->alias(HashHelper::class, HashHelperInterface::class);

        $this->app->singleton(PasswordHasher::class);
        $this->app->alias(PasswordHasher::class, 'core.crypto.password');
        $this->app->alias(PasswordHasher::class, PasswordHasherInterface::class);

        $this->app->singleton(JwtHelper::class);
        $this->app->alias(JwtHelper::class, 'core.crypto.jwt');
        $this->app->alias(JwtHelper::class, JwtHelperInterface::class);

        $this->app->singleton(SodiumHelper::class, function (): SodiumHelper {
            $keyVal = config('core.base::security.cast_key', '');
            $keyb64 = is_scalar($keyVal) ? (string) $keyVal : '';
            if ($keyb64 === '') {
                throw new RuntimeException('Security key [core.base::security.cast_key] is not configured.');
            }

            try {
                return new SodiumHelper($keyb64);
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException(
                    '[core.base::security.cast_key] — ' . $e->getMessage(),
                    previous: $e,
                );
            }
        });
        $this->app->alias(SodiumHelper::class, 'core.crypto.sodium');
        $this->app->alias(SodiumHelper::class, SodiumHelperInterface::class);

        // ── Hybrid P2P ────────────────────────────────────────────
        $this->app->singleton(SodiumHybridP2p::class, function (): SodiumHybridP2p {
            $keyVal = config('core.base::security.key64', '');
            $key = is_scalar($keyVal) ? (string) $keyVal : '';
            if ($key === '') {
                throw new RuntimeException('Security key [core.base::security.key64] is not configured.');
            }

            return new SodiumHybridP2p($key);
        });
        $this->app->alias(SodiumHybridP2p::class, 'core.security.hybrid_p2p');
        $this->app->alias(SodiumHybridP2p::class, SodiumHybridP2pInterface::class);

        // ── Token Blacklist ───────────────────────────────────────
        $this->app->singleton(SecurityTokenBlacklistService::class, fn($app) => new SecurityTokenBlacklistService(
            $app->make(JwtHelper::class),
        ));
        $this->app->alias(SecurityTokenBlacklistService::class, 'core.security.blacklist');
        $this->app->alias(SecurityTokenBlacklistService::class, SecurityTokenBlacklistInterface::class);
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
            // Security Services
            EncryptionService::class,
            EncryptionServiceInterface::class,
            'core.security.encryption',
            // Crypto Helpers
            HashHelper::class,
            HashHelperInterface::class,
            'core.crypto.hash',
            PasswordHasher::class,
            PasswordHasherInterface::class,
            'core.crypto.password',
            JwtHelper::class,
            JwtHelperInterface::class,
            'core.crypto.jwt',
            SodiumHelper::class,
            SodiumHelperInterface::class,
            'core.crypto.sodium',
            // Hybrid P2P
            SodiumHybridP2p::class,
            SodiumHybridP2pInterface::class,
            'core.security.hybrid_p2p',
            // Token Blacklist
            SecurityTokenBlacklistService::class,
            SecurityTokenBlacklistInterface::class,
            'core.security.blacklist',
        ];
    }
}
