<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use Carbon\Carbon;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Slave\Contracts\Master\TokenFlow;
use Throwable;

/**
 * TokenManager — รับผิดชอบการจัดการ Token lifecycle กับ Master Server
 *
 * ทำหน้าที่:
 *  - ขอ access token ใหม่ผ่าน Sodium Cryptography Signature
 *  - จัดเก็บ cache และดึงข้อมูล tokens
 *  - เคลียร์ invalid tokens เมื่อได้รับแจ้ง
 */
class TokenManager
{
    /** TTL ขั้นต่ำของ cache token (วินาที) */
    private const int MIN_CACHE_TTL = 300;

    /** Buffer ที่ตัดออกจาก expires_in ก่อนใช้เป็น TTL (วินาที) */
    private const int CACHE_TTL_BUFFER = 300;

    /** Default expires_in หาก Master ไม่ส่งกลับมา (12 ชั่วโมง) */
    private const int DEFAULT_EXPIRES_IN = 43200;

    public function __construct(
        private readonly string $masterUrl,
        private string $clientId,
        private string $clientSecret,
        private readonly SodiumHelper $sodium,
        private readonly string $signatureSeed,
        private readonly string $publicBox,
        /** ชื่อช่องทางสำหรับเก็บ Access Token Cache (เช่น 'redis', 'session', 'file') - null คือ default */
        private ?string $tokenStoreName = null,
        /** Suffix พิเศษสำหรับต่อท้าย Cache Key เพื่อแยก Context ป้องกันข้อมูลชนกัน (เช่น User ID, Session ID) */
        private string $cacheSuffix = '',
    ) {}

    /**
     * คืน instance ใหม่ที่มีการต่อท้าย Cache Key ป้องกันข้อมูลทับซ้อน
     */
    public function withCacheSuffix(string $suffix): static
    {
        $clone = clone $this;
        $clone->cacheSuffix = trim($suffix);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่เปลี่ยน Driver/Store ในการแคช Token
     */
    public function withTokenStore(?string $storeName): static
    {
        $clone = clone $this;
        $clone->tokenStoreName = $storeName;

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่เปลี่ยน credentials (ใช้รองรับ Fluent Pattern ใน Client)
     */
    public function withCredentials(string $clientId, string $clientSecret): static
    {
        $clone = clone $this;
        $clone->clientId = $clientId;
        $clone->clientSecret = $clientSecret;

        return $clone;
    }

    /**
     * คืน access token ตาม flow ที่กำหนด (รองรับ auto-refresh หากหมดอายุ)
     */
    public function getToken(TokenFlow $flow, string $scope): string
    {
        $cacheKey = $this->cacheKey($flow->cachePrefix(), $scope);
        $cached   = $this->getFromStorage($cacheKey);

        // 🌟 รองรับ Rich Array Payload
        if (\is_array($cached) && isset($cached['access_token'])) {
            return (string) $cached['access_token'];
        }

        // Fallback Legacy Support
        if (\is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheToken($flow, $scope);
    }

    /**
     * ดึง Refresh Token จากช่องทางการจัดเก็บปัจจุบัน (ถ้ามี)
     */
    public function getRefreshToken(TokenFlow $flow, string $scope): ?string
    {
        $cached = $this->getFromStorage($this->cacheKey($flow->cachePrefix(), $scope));

        if (\is_array($cached) && isset($cached['refresh_token'])) {
            return (string) $cached['refresh_token'];
        }

        return null;
    }

    /**
     * ตรวจสอบสถานะการหมดอายุของ Token ด้วย Logic การ Double Verify
     */
    public function isExpired(TokenFlow $flow, string $scope): bool
    {
        $key = $this->cacheKey($flow->cachePrefix(), $scope);
        $cached = $this->tokenStoreName === 'session' 
            ? session($key) 
            : $this->cache()->get($key);

        return $this->isExpiredData($cached);
    }

    /**
     * 🕵️‍♂️ ดึงรายชื่อ Cache Keys ทั้งหมดใน Manifest ออกมาดู
     * @return array<int, string>
     */
    public function getManifestKeys(): array
    {
        $manifestKey = "master_manifest:{$this->clientId}";
        
        $keys = $this->tokenStoreName === 'session'
            ? session($manifestKey, [])
            : $this->cache()->get($manifestKey, []);

        return \is_array($keys) ? $keys : [];
    }

    /**
     * 🧪 ดึงข้อมูลเชิงลึกของ Token ทุกตัวที่มีอยู่ ณ ปัจจุบันมาแสดงผล (สำหรับ Debug)
     * @return array<string, mixed>
     */
    public function debugAllTokens(): array
    {
        $keys = $this->getManifestKeys();
        $dump = [];

        foreach ($keys as $key) {
            if (\is_string($key) && $key !== '') {
                $dump[$key] = $this->getFromStorage($key);
            }
        }

        return $dump;
    }

    /**
     * 💥 คำสั่งล้างบาง: ลบ Token ทั้งหมดของ Client นี้ (ทุก Flow และทุก Scope) ให้หมดจด
     */
    public function clearAll(): void
    {
        $manifestKey = "master_manifest:{$this->clientId}";
        
        // ดึงรายชื่อกุญแจ (Manifest) ทั้งหมดที่ระบบเคยแอบจดบันทึกไว้
        $keys = $this->tokenStoreName === 'session'
            ? session($manifestKey, [])
            : $this->cache()->get($manifestKey, []);

        if (! \is_array($keys)) {
            return; // ไม่เคยมีบันทึก = สะอาดอยู่แล้ว
        }

        // 🚀 กวาดล้างทุก Token ในรายชื่ออย่างเป็นระบบและปลอดภัย 100%
        foreach ($keys as $targetKey) {
            if (\is_string($targetKey) && $targetKey !== '') {
                $this->forgetFromStorage($targetKey);
            }
        }

        // ลบตัวสารบัญ Manifest เองออกเป็นลำดับสุดท้าย
        $this->forgetFromStorage($manifestKey);
    }

    /**
     * ลบ token data ทิ้งสิ้นซาก (รองรับทั้ง Session wrapper และ Cache engine)
     */
    public function clear(TokenFlow $flow, string $scope): void
    {
        $this->forgetFromStorage($this->cacheKey($flow->cachePrefix(), $scope));
    }

    /**
     * บันทึกชุดข้อมูลลงระบบจัดเก็บที่เลือกไว้อย่างสมบูรณ์ (เลียนแบบ BackendApi Pattern)
     */
    public function store(array $data, TokenFlow $flow, string $scope = ''): string
    {
        $accessToken = $data['access_token'] ?? null;
        $tokenType   = $data['token_type'] ?? 'Bearer';
        $rawExpires  = $data['expires_in'] ?? self::DEFAULT_EXPIRES_IN;

        if (! \is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException("Token storage security violation: missing 'access_token'.");
        }

        $expiresIn = \is_int($rawExpires) ? $rawExpires : self::DEFAULT_EXPIRES_IN;
        $cacheTtl  = max(self::MIN_CACHE_TTL, $expiresIn - self::CACHE_TTL_BUFFER);

        $fullPayload = [
            'access_token'  => $accessToken,
            'token_type'    => $tokenType,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in'    => $expiresIn,
            'expires_at'    => now()->addSeconds($expiresIn)->toIso8601String(),
        ];

        $this->storeToStorage(
            $this->cacheKey($flow->cachePrefix(), $scope),
            $fullPayload,
            $cacheTtl
        );

        return $accessToken;
    }

    /**
     * ขอ access token ใหม่จาก Master ตาม flow ที่ระบุ พร้อม cache ผลลัพธ์
     */
    private function fetchAndCacheToken(TokenFlow $flow, string $scope): string
    {
        $payload = [
            'grant_type'    => $flow->grantType(),
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $scope,
        ];

        $headers = [
            'X-Timestamp' => now()->toIso8601String(),
            'X-Client-ID' => $this->clientId,
        ];

        $signatureKeyPair = $this->sodium->generateSignatureKeyPair($this->signatureSeed);
        $privateSignKey = $signatureKeyPair['secret'] ?? '';

        if ($privateSignKey === '') {
            throw new RuntimeException('Invalid signature keypair: missing secret.');
        }

        // ทำการเข้ารหัสและ Sign Payload ตาม Protocol ความปลอดภัยระดับสูง
        $encryptedPayload = $this->sodium->hybridEncrypt($payload, $this->publicBox);
        $headers['X-Signature'] = $this->sodium->sign([...$payload, ...$headers], $privateSignKey);

        // เคลียร์ Memory ทันทีเพื่อความปลอดภัยสูงสุด
        \sodium_memzero($privateSignKey);
        if (\is_string($signatureKeyPair['secret'] ?? null)) {
            \sodium_memzero($signatureKeyPair['secret']);
        }

        try {
            $response = Http::asForm()
                ->withHeaders($headers)
                ->post(
                    "{$this->masterUrl}{$flow->endpoint()}",
                    ['encrypted_payload' => $encryptedPayload],
                );
        } catch (Throwable $e) {
            Log::critical('Master Token Fetch Error: Connection Failed', [
                'client_id' => $this->clientId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($response->failed()) {
            Log::error('Master Auth Failed', [
                'client_id' => $this->clientId,
                'flow'      => $flow->value,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new RuntimeException('Could not authenticate with Master Server.');
        }

        $body = $response->json();
        if (! \is_array($body)) {
            throw new RuntimeException('Master Server returned invalid JSON response.');
        }

        $data = $flow->unwrapBody($body);

        return $this->store($data, $flow, $scope);
    }

    /**
     * สร้าง Key สำหรับ Cache
     */
    private function cacheKey(string $prefix, string $scope): string
    {
        $base = "{$prefix}:{$this->clientId}:" . md5($scope);

        return $this->cacheSuffix === ''
            ? $base
            : "{$base}:{$this->cacheSuffix}";
    }

    /**
     * Helper สำหรับการเรียกใช้งาน Cache Store
     */
    private function cache(): CacheRepository
    {
        return $this->tokenStoreName !== null
            ? Cache::store($this->tokenStoreName)
            : Cache::store();
    }

    /**
     * 🔥 Helper พิเศษ: บันทึกข้อมูลลง Storage
     * (สลับระหว่าง Native Session แบบ BackendApi กับ Laravel Cache อัตโนมัติ)
     */
    private function storeToStorage(string $key, array $payload, int $ttl): void
    {
        if ($this->tokenStoreName === 'session') {
            session([$key => $payload]); // 🚀 Direct Native Session Store (Flexible & Literal)
        } else {
            $this->cache()->put($key, $payload, $ttl);
        }

        // 🔥 บันทึกกุญแจลงสารบัญอัตโนมัติ เพื่อให้ระบบรู้ว่าจะตามไปลบทั้งหมดได้ที่ไหน!
        $this->recordToManifest($key);
    }

    /**
     * 🔥 Helper ลงทะเบียนประวัติการสร้าง Cache Keys
     */
    private function recordToManifest(string $key): void
    {
        $manifestKey = "master_manifest:{$this->clientId}";
        
        $keys = $this->tokenStoreName === 'session'
            ? session($manifestKey, [])
            : $this->cache()->get($manifestKey, []);

        $keys = \is_array($keys) ? $keys : [];
        
        if (! \in_array($key, $keys, true)) {
            $keys[] = $key;
            
            if ($this->tokenStoreName === 'session') {
                session([$manifestKey => $keys]);
            } else {
                // บันทึกสารบัญไว้เป็นระยะยาว (เช่น 30 วัน) เพื่อเป็นฐานข้อมูลอ้างอิง
                $this->cache()->put($manifestKey, $keys, 2592000); 
            }
        }
    }

    /**
     * 🔥 Helper พิเศษ: ดึงข้อมูล พร้อมระบบ Smart Auto-Pruning
     */
    private function getFromStorage(string $key): mixed
    {
        $cached = $this->tokenStoreName === 'session'
            ? session($key) // 🚀 Direct Native Session Retrieve
            : $this->cache()->get($key);

        // 💡 พลังขับเคลื่อนอัจฉริยะ: ป้องกัน Session ค้างเติ่งตลอดกาล (เพราะ Session ไม่มี Auto TTL แบบ Cache)
        // หากตรวจพบว่าหมดอายุตาม Double Check Algorithm จะสั่งทำลายและคืน Null เพื่อกระตุ้น Auto-Refresh ทันที!
        if ($cached !== null && $this->isExpiredData($cached)) {
            $this->forgetFromStorage($key);
            return null; 
        }

        return $cached;
    }

    /**
     * 🔥 Helper พิเศษ: ล้างข้อมูลทิ้งถาวร
     */
    private function forgetFromStorage(string $key): void
    {
        if ($this->tokenStoreName === 'session') {
            session()->forget($key); // 🚀 Native Session Destruction
            return;
        }

        $this->cache()->forget($key);
    }

    /**
     * 🔥 Core Expired Validator (Double Check Security Layer)
     */
    private function isExpiredData(mixed $cached): bool
    {
        if ($cached === null) {
            return true;
        }

        if (\is_string($cached)) {
            return false; // Legacy valid context
        }

        if (\is_array($cached) && isset($cached['expires_at'])) {
            // คำนวณความปลอดภัย: เวลาตอนนี้ + Buffer (5 นาที) ทะลุจุดหมดอายุหรือยัง?
            return now()->addSeconds(self::CACHE_TTL_BUFFER)->gte(Carbon::parse($cached['expires_at']));
        }

        return true; // ปลอดภัยไว้ก่อน ถ้าผิดพลาดให้ถือว่าตาย
    }
}
