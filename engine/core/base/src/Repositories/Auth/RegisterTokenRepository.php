<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Auth;

use App\Models\RegisterToken;
use Core\Base\Repositories\Eloquent\BaseRepository;

/**
 * RegisterTokenRepository — Data Access Layer สำหรับ RegisterToken
 *
 * Extend BaseRepository เพื่อรับ operations มาตรฐานครบชุด
 * Repository นี้ implement เฉพาะ domain-specific methods ของ RegisterToken
 *
 * ตัวอย่างการใช้งาน base methods:
 * ```php
 * $token = $repo->create(['email' => '...', 'expires_at' => now()->addHours(24)]);
 * $tokens = $repo->paginate(20);
 * ```
 */
class RegisterTokenRepository extends BaseRepository implements RegisterTokenInterface
{
    public function __construct(RegisterToken $model)
    {
        parent::__construct($model);
    }

    // =========================================================================
    // Token Validation
    // =========================================================================

    /**
     * ค้นหา token ที่ยังใช้งานได้ (ไม่ revoked และไม่หมดอายุ)
     *
     * ใช้ newQuery() เพื่อรองรับ criteria pattern และ stateless design
     * Select เฉพาะ id, email เพื่อลด data transfer — caller ใช้แค่ email
     *
     * @param  string  $id  UUID ของ RegisterToken
     * @return RegisterToken|null token ที่ valid หรือ null ถ้าไม่พบ
     */
    public function findValidToken(string $id): ?RegisterToken
    {
        /** @var RegisterToken|null */
        return $this->newQuery()
            ->where('id', $id)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->select(['id', 'email'])
            ->first();
    }

    // =========================================================================
    // Token Query
    // =========================================================================

    /**
     * ค้นหา token ที่ยังใช้งานได้สำหรับ email ที่ระบุ
     *
     * ใช้สำหรับตรวจสอบ duplicate ก่อนสร้าง token ใหม่
     * Select เฉพาะ id, expires_at เพื่อลด data transfer
     *
     * @param  string  $email  อีเมลที่ต้องการค้นหา
     * @return RegisterToken|null token ที่ valid หรือ null ถ้าไม่พบ
     */
    public function findActiveByEmail(string $email): ?RegisterToken
    {
        /** @var RegisterToken|null */
        return $this->newQuery()
            ->where('email', $email)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->select(['id', 'expires_at'])
            ->first();
    }

    /**
     * สร้าง RegisterToken ใหม่สำหรับ email ที่ระบุ
     *
     * @param  string  $email  อีเมลที่ต้องการลงทะเบียน
     * @param  int  $ttlSeconds  อายุของ token (วินาที)
     * @return RegisterToken token ที่สร้างใหม่
     */
    public function createToken(string $email, int $ttlSeconds = 86400): RegisterToken
    {
        /** @var RegisterToken */
        return $this->newQuery()->create([
            'email' => $email,
            'data' => json_encode_th([]),
            'token' => hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => now()->addSeconds($ttlSeconds)->toDateTimeString(),
            'revoked' => false,
        ]);
    }

    // =========================================================================
    // Token Lifecycle
    // =========================================================================

    /**
     * ยกเลิกการใช้งาน token (revoke) อย่างปลอดภัย
     *
     * ตรวจสอบเงื่อนไขก่อน update เพื่อป้องกัน race condition:
     * - ต้องไม่ถูก revoke ไปแล้ว
     * - ต้องยังไม่หมดอายุ
     *
     * @param  string  $id  UUID ของ RegisterToken
     * @return bool true ถ้า revoke สำเร็จ (affected rows > 0)
     */
    public function revokeToken(string $id): bool
    {
        return (bool) $this->newQuery()
            ->where('id', $id)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->update(['revoked' => true]);
    }
}
