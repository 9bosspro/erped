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

    /** Endpoint สำหรับขอ token */
    public function endpoint(): string
    {
        return match ($this) {
            self::OAuth => '/oauth/token',
            self::Jwt => '/api/v1/jwt/token',
            self::Personal => '/api/v1/personal/token',
        };
    }

    /** grant_type ที่ส่งใน payload */
    public function grantType(): string
    {
        return match ($this) {
            self::OAuth => 'client_credentials',
            self::Jwt => 'client_credentials_jwt',
            self::Personal => 'personal_access_token',
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
            self::OAuth => $body,
            self::Jwt => \is_array($body['data'] ?? null) ? $body['data'] : [],
            self::Personal => \is_array($body['data'] ?? null) ? $body['data'] : [],
        };
    }
}
