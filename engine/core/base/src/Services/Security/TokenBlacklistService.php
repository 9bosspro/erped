<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

use Core\Base\Services\Security\Contracts\TokenBlacklistServiceInterface;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * TokenBlacklistService — บริการจัดการ Blacklist ของ JWT Token ผ่าน Redis
 *
 * ═══════════════════════════════════════════════════════════════
 *  สถาปัตยกรรม: Performance-First Strategy
 * ═══════════════════════════════════════════════════════════════
 *
 * Service นี้ทำหน้าที่จัดการการถอนสิทธิ์ (Revoke) ของ JWT Token
 * โดยใช้ Redis เป็นที่เก็บข้อมูล JTI (JWT ID) พร้อมวันหมดอายุ (TTL) อัตโนมัติ
 * เพื่อเพิ่มประสิทธิภาพในระดับ Micro-seconds และรักษาระดับความปลอดภัยสูงสุด
 *
 * ✨ ใช้ร่วมกับ Middleware เพื่อตรวจสอบสิทธิ์การใช้งานในทุก Request
 */
final class TokenBlacklistService implements TokenBlacklistServiceInterface
{
    /** @var string คำนำหน้าคีย์ใน Redis (ดึงจาก config หรือค่าเริ่มต้น) */
    private readonly string $prefix;

    /** @var string ชื่อการเชื่อมต่อ Redis ที่ใช้งาน */
    private readonly string $connection;

    /**
     * สร้าง TokenBlacklistService instance
     *
     * @param  JwtHelper  $jwtHelper  ตัวช่วยจัดการ JWT
     */
    public function __construct(
        private readonly JwtHelper $jwtHelper,
    ) {
        $conn = config('core.base::crypto.jwt.blacklist_connection', 'default');
        $this->connection = \is_string($conn) ? $conn : 'default';

        $pref = config('core.base::crypto.jwt.blacklist_prefix', 'jwt:blacklist:');
        $this->prefix = \is_string($pref) ? $pref : 'jwt:blacklist:';
    }

    /**
     * Revoke Token โดยใช้ JTI
     *
     * @param  string  $jti  รหัสประจำตัว Token
     * @param  int  $ttl  ระยะเวลาที่ต้องการเก็บ (วินาที)
     */
    public function revoke(string $jti, int $ttl): void
    {
        if ($jti === '' || $ttl <= 0) {
            Log::warning('TOKEN_BLACKLIST_REVOKE_SKIPPED', [
                'jti' => $jti === '' ? '(empty)' : $jti,
                'ttl' => $ttl,
                'reason' => $jti === '' ? 'empty JTI' : 'TTL <= 0 (token already expired)',
            ]);

            return;
        }

        Redis::connection($this->connection)
            ->setex($this->prefix.$jti, $ttl, '1');
    }

    /**
     * Revoke Token จากตัวแปรชุดตัวอักษรโดยตรง
     *
     * @param  string  $token  ชุดตัวอักษร JWT Token
     */
    public function revokeToken(string $token): bool
    {
        // ตรวจ signature ก่อนเสมอ — ป้องกัน attacker inject JTI ที่ต้องการ blacklist
        if (! $this->jwtHelper->validateSignatureOnly($token)) {
            return false;
        }

        $parsed = $this->jwtHelper->parseUnvalidated($token);

        if ($parsed === null) {
            return false;
        }

        $jti = $parsed->claims()->get('jti');
        $exp = $parsed->claims()->get('exp');

        if (! \is_string($jti) || $jti === '') {
            return false;
        }

        if (! ($exp instanceof DateTimeImmutable)) {
            throw new RuntimeException('Token security policy violation: Revoked token must have an "exp" claim.');
        }

        // คำนวณเวลาที่เหลือ (TTL)
        $ttl = $exp->getTimestamp() - time();

        if ($ttl <= 0) {
            return false; // Token หมดอายุไปแล้ว ไม่จำเป็นต้องนำเข้า Blacklist
        }

        $this->revoke($jti, $ttl);

        return true;
    }

    /**
     * ตรวจสอบว่า JTI ถูกระงับสิทธิ์หรือไม่
     *
     * @param  string  $jti  รหัสประจำตัว Token
     */
    public function isRevoked(string $jti): bool
    {
        if ($jti === '') {
            return true;
        }

        return Redis::connection($this->connection)
            ->exists("{$this->prefix}{$jti}") > 0;
    }

    /**
     * ตรวจสอบว่าชุดตัวอักษร Token ถูกระงับสิทธิ์หรือไม่
     *
     * @param  string  $token  ชุดตัวอักษร JWT Token
     */
    public function isTokenRevoked(string $token): bool
    {
        // ✅ 1. ตรวจ Signature เบื้องต้นก่อนเข้า Redis (ป้องกัน DoS attack)
        if (! $this->jwtHelper->validateSignatureOnly($token)) {
            return true; // ถ้า signature ผิด ถือว่าถูกระงับสิทธิ์/ใช้งานไม่ได้
        }

        // 2. ดึงข้อมูล JTI
        $parsed = $this->jwtHelper->parseUnvalidated($token);

        if ($parsed === null) {
            return true;
        }

        $jti = $parsed->claims()->get('jti');

        // 3. ตรวจสอบใน Redis
        return ! \is_string($jti) || $this->isRevoked($jti);
    }

    /**
     * คืนค่าสถานะ Token จาก Blacklist (ยกเลิกการ Revoke)
     *
     * @param  string  $jti  รหัสประจำตัว Token
     */
    public function restore(string $jti): void
    {
        if ($jti === '') {
            return;
        }

        Redis::connection($this->connection)
            ->del($this->prefix.$jti);
    }
}
