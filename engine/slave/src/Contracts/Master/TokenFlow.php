<?php

declare(strict_types=1);

namespace Slave\Contracts\Master;

/**
 * ประเภทของ token flow ที่รองรับในการติดต่อ Master Server
 *
 * แต่ละ case เก็บ config ครบในตัวเอง — เพิ่ม flow ใหม่เพียงเพิ่ม case
 * แล้ว implement 4 methods ด้านล่างให้ครบ (PHP จะ error ถ้าลืม)
 */
enum TokenFlow: string
{
    /** OAuth2 Client Credentials — /oauth/token */
    case OAuth = 'oauth';

    /** JWT Client Credentials — /api/v1/jwt/token */
    case Jwt = 'jwt';

    /** Personal Access Token — /api/v1/personal/token */
    case Personal = 'personal';

    /** Password Grant — /api/v1/password/token */
    case Password = 'password';

    /** Endpoint สำหรับขอ token */
    public function endpoint(): string
    {
        return match ($this) {
            self::OAuth => '/oauth/token',
            self::Jwt => '/api/v1/jwt/token',
            self::Personal => '/api/v1/auth/personal/login-social',
            self::Password => '/oauth/token',
        };
    }

    /** Endpoint สำหรับลบ token */
    public function revokeTokenEndpoint(): string
    {
        return match ($this) {
            self::OAuth => '/oauth/revoke',
            self::Jwt => '/api/v1/jwt/token/revoke',
            self::Personal => '/api/v1/personal/token/revoke',
            self::Password => '/oauth/revoke',
        };
    }

    /**
     * ตรวจสอบว่า Flow นี้ต้องการ Body Token เพิ่มเติมหรือไม่ ( นอกเหนือจาก Client Credentials )
     */
    public function isNeedBodyToken(): bool
    {
        return match ($this) {
            self::OAuth => false,
            self::Jwt => false,
            self::Personal => true,
            self::Password => false,
        };
    }

    /**
     * Endpoint สำหรับขอข้อมูล token
     */
    public function meEndpoint(): string
    {
        return match ($this) {
            self::OAuth => '/oauth/token/me',
            self::Jwt => '/api/v1/jwt/token/me',
            self::Personal => '/api/v1/personal/token/me',
            self::Password => '/oauth/token/me',
        };
    }

    // payload
    /*    public function payload(): array
       {
           return match ($this) {
               self::OAuth => [
                   'grant_type' => $this->grantType(),
                   'client_id' =>config('slave::client.client_id', ''),
                   'client_secret' => config('slave::client.client_secret', ''),
               ],
               self::Jwt => [
                   'grant_type' => $this->grantType(),
                   'client_id' => config('slave::client.client_id', ''),
                   'client_secret' => config('slave::client.client_secret', ''),
               ],
               self::Personal => [
                   'grant_type' => $this->grantType(),
                   'client_id' => config('slave::client.client_id', ''),
                   'client_secret' => config('slave::client.client_secret', ''),
               ],
           };
       } */

    /** grant_type ที่ส่งใน payload */
    public function grantType(): string
    {
        return match ($this) {
            self::OAuth => 'client_credentials',
            self::Jwt => 'client_credentials_jwt',
            self::Personal => 'personal_access',
            self::Password => 'password',
        };
    }

    public function isUserAuthToken(): bool
    {
        return match ($this) {
            self::OAuth => false,
            self::Jwt => false,
            self::Personal => true,
            self::Password => true,
        };
    }

    /** prefix ของ cache key */
    public function cachePrefix(): string
    {
        // 🚀 DRY Optimization: ใช้ backed value ('oauth', 'jwt') ต่อท้ายอัตโนมัติ
        // เพิ่ม Case ใหม่ในอนาคตก็ไม่ต้องตามมาแก้ไขจุดนี้ครับ
        return "master_token:{$this->value}";
    }

    /**
     * แกะ data จาก response body ตาม format ของแต่ละ flow
     *
     * OAuth: คืน body ตรง ๆ
     * JWT:   คืน body['data'] — ถ้าโครงสร้างผิด คืน [] ให้ caller จัดการ
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function unwrapBody(array $body): array
    {
        return match ($this) {
            self::OAuth => \is_array($body['data'] ?? null) ? $body['data'] : [],
            self::Jwt => \is_array($body['data'] ?? null) ? $body['data'] : [],
            self::Personal => \is_array($body['data'] ?? null) ? $body['data'] : [],
            self::Password => \is_array($body['data'] ?? null) ? $body['data'] : [],
        };
    }

    /**
     * ตรวจสอบว่า Flow นี้รองรับการเก็บ Token แบบ Session หรือไม่
     */
    public function isSessionToken(): bool
    {
        return match ($this) {
            self::OAuth => false,
            self::Jwt => false,
            self::Personal => true,
            self::Password => true,
        };
    }

    /**
     * Flow ที่ต้องการ Username/Password (Password Grant)
     */
    public function isPasswordGrant(): bool
    {
        return $this === self::Password;
    }

    /**
     * Flow ที่ไม่ต้องใช้ User Credentials (Client Credentials)
     */
    public function isClientCredentials(): bool
    {
        return ! $this->isUserAuthToken();
    }
}
