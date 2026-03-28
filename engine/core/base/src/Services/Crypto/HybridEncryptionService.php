<?php

declare(strict_types=1);

namespace Core\Base\Services\Crypto;

use Core\Base\Support\Helpers\Crypto\EncryptionHelper;
use Core\Base\Support\Helpers\Crypto\RsaHelpers;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use RuntimeException;
use Exception;

/**
 * HybridEncryptionService — Facade Service รวมการเข้ารหัสหลายระดับ
 *
 * ═══════════════════════════════════════════════════════════════
 *  สถาปัตยกรรม: Thin Delegation Layer
 * ═══════════════════════════════════════════════════════════════
 *
 * Service นี้ **delegate** ไปยัง Helper layer:
 *  - Symmetric encryption → EncryptionHelper (core.crypto.crypt)
 *  - RSA hybrid encryption → RsaHelpers      (core.crypto.rsa)
 *
 * 6 ระดับการเข้ารหัส:
 *  1. encrypt/decrypt              — RSA + AES-256-GCM (asymmetric, delegate → RsaHelpers)
 *  2. aesEncrypt/aesDecrypt        — PBKDF2 + AES-256-GCM (delegate → EncryptionHelper)
 *  3. cryptEncrypt/cryptDecrypt    — Expiring encryption (delegate → EncryptionHelper)
 *  4. rsaEncrypt/rsaDecrypt        — RSA + AES-GCM พร้อม expiration
 *  5. encrypts/decrypts            — AES-256-GCM ด้วย internal key (Laravel Encrypter)
 *  6. encryptWithKey/decryptWithKey— AES-256-CBC ด้วย password (Laravel Encrypter)
 *
 * ⚠️ สำหรับโค้ดใหม่ ให้ inject EncryptionHelper / RsaHelpers โดยตรง
 *    แทนการใช้ Service นี้ — เพื่อ explicit dependency ที่ชัดเจน
 */
class HybridEncryptionService
{
    private readonly RsaHelpers $rsa;

    private readonly Encrypter $encrypter;

    public function __construct(
        RsaHelpers $rsaHelpers,
        private readonly EncryptionHelper $encryptionHelper,
    ) {
        // ใช้ RsaHelpers แต่ configure ด้วย passport keys (ไม่ใช่ crypto.rsa.*)
        $this->rsa = $rsaHelpers->withKeys(
            config('passport.private_key'),
            config('passport.public_key'),
        );

        // Encrypter สำหรับ encrypts()/decrypts() — key จาก config
        $secret = (string) config('crypto.encrypter_secret', '');
        $key = hash('sha256', $secret, true);
        $this->encrypter = new Encrypter($key, 'aes-256-gcm');
    }

    // ─── RSA + AES-GCM (Asymmetric Hybrid) ──────────────────────

    /**
     * Encrypt ด้วย RSA + AES-256-GCM (delegate → RsaHelpers::hybridEncryptEnvelope)
     *
     * @param  string|array  $data  ข้อมูล (array จะถูก json_encode)
     * @return array{key: string, iv: string, tag: string, data: string}
     */
    public function encrypt(string|array $data): array
    {
        $envelope = $this->rsa->hybridEncryptEnvelope($data);

        // แปลง format ให้ตรงกับ API เดิม
        return [
            'key' => $envelope['encrypted_key'],
            'iv' => $envelope['iv'],
            'tag' => $envelope['tag'],
            'data' => $envelope['data'],
        ];
    }

    /**
     * Decrypt RSA + AES-256-GCM payload
     *
     * @param  array{key: string, iv: string, tag: string, data: string}  $payload
     * @return string|array ข้อมูลต้นฉบับ
     */
    public function decrypt(array $payload): string|array
    {
        $required = ['key', 'iv', 'tag', 'data'];
        $missing = array_diff($required, array_keys($payload));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Missing payload fields: ' . implode(', ', $missing),
            );
        }

        // แปลง format กลับเป็น envelope ของ RsaHelpers
        $envelope = [
            'v' => 1,
            'cipher' => 'aes-256-gcm',
            'encrypted_key' => $payload['key'],
            'iv' => $payload['iv'],
            'tag' => $payload['tag'],
            'data' => $payload['data'],
        ];

        $result = $this->rsa->hybridDecryptEnvelope($envelope);

        return is_array($result) ? $result : (string) $result;
    }

    // ─── PBKDF2 + AES-GCM (Symmetric) ──────────────────────────

    /**
     * เข้ารหัสด้วย PBKDF2 + AES-256-GCM (delegate → EncryptionHelper)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเข้ารหัส
     * @param  string|null  $key  กุญแจ (default: PBKDF shared secret)
     * @return string ข้อมูลที่เข้ารหัสแล้ว (base64)
     */
    public function aesEncrypt(mixed $data, ?string $key = null): string
    {
        $resolvedKey = $key ?? (string) config('services.pbkdf.secret', '');

        if ($resolvedKey === '') {
            throw new InvalidArgumentException(
                'Encryption key is required. Set PBKDF_SECRET in .env or pass a key explicitly.',
            );
        }

        return $this->encryptionHelper->encryptWithPassword($data, $resolvedKey);
    }

    /**
     * ถอดรหัสข้อมูลที่เข้ารหัสด้วย aesEncrypt
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัสแล้ว (base64)
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed ข้อมูลต้นฉบับ
     */
    public function aesDecrypt(string $encrypted, ?string $key = null): mixed
    {
        $resolvedKey = $key ?? (string) config('services.pbkdf.secret', '');

        if ($resolvedKey === '') {
            throw new InvalidArgumentException(
                'Encryption key is required. Set PBKDF_SECRET in .env or pass a key explicitly.',
            );
        }

        return $this->encryptionHelper->decryptWithPassword($encrypted, $resolvedKey);
    }

    // ─── Expiring Encryption ────────────────────────────────────

    /**
     * เข้ารหัสพร้อมกำหนดอายุ (delegate → EncryptionHelper::encryptExpiring)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $key  กุญแจ (default: APP_KEY)
     * @param  int  $delay  อายุเป็นวินาที (default: 30)
     * @return string  encrypted payload (base64)
     */
    public function cryptEncrypt(mixed $data, ?string $key = null, int $delay = 30): string
    {
        return $this->encryptionHelper->encryptExpiring($data, $delay, $key);
    }

    /**
     * ถอดรหัสที่มี expiration (delegate → EncryptionHelper::decryptExpiring)
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัสจาก cryptEncrypt()
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส, default: APP_KEY)
     * @return mixed  ข้อมูลต้นฉบับ
     *
     * @throws RuntimeException เมื่อ payload ไม่ถูกต้องหรือหมดอายุ
     */
    public function cryptDecrypt(string $encrypted, ?string $key = null): mixed
    {
        return $this->encryptionHelper->decryptExpiring($encrypted, $key);
    }

    /**
     * เข้ารหัสพร้อม expiration ด้วย RSA + AES-GCM
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเข้ารหัส
     * @param  int  $delay  อายุเป็นวินาที (default: 30)
     * @return array{key: string, iv: string, tag: string, data: string}
     */
    public function rsaEncrypt(mixed $data, int $delay = 30): array
    {
        $payload = [
            'data' => $data,
            'exp' => time() + max(0, $delay),
        ];

        return $this->encrypt($payload);
    }

    /**
     * ถอดรหัส RSA + AES-GCM payload พร้อมตรวจ expiration
     *
     * @param  array{key: string, iv: string, tag: string, data: string}  $encrypted
     * @return mixed ข้อมูลต้นฉบับ
     *
     * @throws RuntimeException เมื่อ payload ไม่ถูกต้องหรือ token หมดอายุ
     */
    public function rsaDecrypt(array $encrypted): mixed
    {
        $payload = $this->decrypt($encrypted);

        if (! is_array($payload) || ! isset($payload['exp'], $payload['data'])) {
            throw new RuntimeException('Invalid encrypted payload format');
        }

        if (time() > $payload['exp']) {
            throw new RuntimeException('Token expired');
        }

        return $payload['data'];
    }

    // ─── Laravel Encrypter Wrappers ─────────────────────────────

    /**
     * เข้ารหัสด้วย password (AES-256-CBC ผ่าน Laravel Encrypter)
     *
     * ⚠️ $password ต้องยาว 32 bytes พอดี (AES-256 key length)
     *
     * @param  mixed  $data  ข้อมูล (non-string จะถูก json_encode)
     * @param  string  $password  กุญแจ 32 bytes
     * @return string  encrypted string
     */
    public function encryptWithKey(mixed $data, string $password): string
    {
        if (! is_string($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $encrypter = new Encrypter($password, 'aes-256-cbc');

        return $encrypter->encryptString($data);
    }

    /**
     * เข้ารหัสด้วย internal key (AES-256-GCM via Laravel Encrypter)
     *
     * @param  mixed  $value  ข้อมูลที่ต้องการเข้ารหัส (จะถูก serialize อัตโนมัติ)
     * @return string  encrypted payload string
     */
    public function encrypts(mixed $value): string
    {
        return $this->encrypter->encrypt($value);
    }

    /**
     * ถอดรหัสด้วย internal key (AES-256-GCM via Laravel Encrypter)
     *
     * @param  string  $payload  ข้อมูลที่เข้ารหัสจาก encrypts()
     * @return mixed  ข้อมูลต้นฉบับ หรือ null เมื่อ decrypt ไม่สำเร็จ
     */
    public function decrypts(string $payload): mixed
    {
        try {
            return $this->encrypter->decrypt($payload);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * ถอดรหัสด้วย password (AES-256-CBC ผ่าน Laravel Encrypter)
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัสแล้ว
     * @param  string  $password  กุญแจ 32 bytes (ต้องตรงกับตอนเข้ารหัส)
     * @return string  ข้อมูลต้นฉบับ
     */
    public function decryptWithKey(string $encrypted, string $password): string
    {
        $encrypter = new Encrypter($password, 'aes-256-cbc');

        return $encrypter->decryptString($encrypted);
    }
}
