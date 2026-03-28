<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\EncryptionHelperInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * CryptHelper — Symmetric Encryption Helper ที่สมบูรณ์ครบวงจร
 *
 * ═══════════════════════════════════════════════════════════════
 *  AES-256-GCM  (แนะนำ — Authenticated Encryption)
 * ═══════════════════════════════════════════════════════════════
 *  encrypt($data)               — เข้ารหัส AES-256-GCM + APP_KEY
 *  decrypt($encrypted)          — ถอดรหัส AES-256-GCM
 *  encryptWithAad($data, $aad)  — เข้ารหัส GCM + AAD (bind context)
 *  decryptWithAad($enc, $aad)   — ถอดรหัส GCM + AAD
 *
 * ═══════════════════════════════════════════════════════════════
 *  AES-256-CBC + HMAC  (สำหรับ compatibility)
 * ═══════════════════════════════════════════════════════════════
 *  encryptCbc($data)            — เข้ารหัส CBC + HMAC-SHA256
 *  decryptCbc($encrypted)       — ถอดรหัส CBC (verify MAC first)
 *
 * ═══════════════════════════════════════════════════════════════
 *  XChaCha20-Poly1305  (modern — via libsodium)
 * ═══════════════════════════════════════════════════════════════
 *  encryptSodium($data)         — เข้ารหัส XChaCha20-Poly1305
 *  decryptSodium($encrypted)    — ถอดรหัส XChaCha20-Poly1305
 *
 * ═══════════════════════════════════════════════════════════════
 *  Sealed Box  (anonymous encryption — ไม่ต้องแชร์ secret key)
 * ═══════════════════════════════════════════════════════════════
 *  generateSodiumKeyPair()      — สร้าง X25519 key pair
 *  sealEncrypt($data, $pubKey)  — encrypt ด้วย public key (ใครก็ encrypt ได้)
 *  sealDecrypt($enc, $keyPair)  — decrypt ด้วย key pair (เฉพาะเจ้าของ)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Password-Based  (PBKDF2 + AES-256-GCM)
 * ═══════════════════════════════════════════════════════════════
 *  encryptWithPassword($data, $pw)   — derive key จาก password แล้ว encrypt
 *  decryptWithPassword($enc, $pw)    — derive key จาก password แล้ว decrypt
 *
 * ═══════════════════════════════════════════════════════════════
 *  Deterministic Encryption  (searchable encrypted fields)
 * ═══════════════════════════════════════════════════════════════
 *  encryptDeterministic($data)  — input เดียวกัน → output เดียวกัน (SIV-like)
 *  decryptDeterministic($enc)   — ถอดรหัส deterministic
 *
 * ═══════════════════════════════════════════════════════════════
 *  Expiring / URL-Safe / Stream
 * ═══════════════════════════════════════════════════════════════
 *  encryptExpiring($data, $ttl)      — encrypt พร้อม TTL
 *  decryptExpiring($encrypted)       — decrypt + ตรวจ expiration
 *  encryptUrlSafe($data)             — URL-safe output
 *  decryptUrlSafe($encrypted)        — decode URL-safe แล้ว decrypt
 *  encryptStream($in, $out)          — encrypt stream/file ขนาดใหญ่
 *  decryptStream($in, $out)          — decrypt stream/file ขนาดใหญ่
 *
 * ═══════════════════════════════════════════════════════════════
 *  Key Management / Utility
 * ═══════════════════════════════════════════════════════════════
 *  autoDecrypt($encrypted)           — auto-detect cipher แล้ว decrypt
 *  reEncrypt($encrypted, $old, $new) — key rotation (decrypt+encrypt)
 *  generateKey()                     — สร้าง random AES key
 *  generateSodiumKeyPair()           — สร้าง X25519 key pair
 *
 * ─── ความปลอดภัย ────────────────────────────────────────────
 *  - random IV/nonce/salt ทุกครั้ง (ยกเว้น deterministic mode)
 *  - GCM + AAD สำหรับ context binding
 *  - CBC ใช้ Encrypt-then-MAC (verify MAC ก่อน decrypt)
 *  - sodium_memzero() ล้าง derived key หลังใช้
 *  - ป้องกัน PBKDF2 iteration downgrade
 *  - Stream encryption ทำงานเป็น chunk ไม่โหลดทั้งไฟล์
 */
final class EncryptionHelper
{
    use ParsesEncryptionKey;

    // ─── Constants ──────────────────────────────────────────────

    private const CIPHER_GCM = 'aes-256-gcm';

    private const CIPHER_CBC = 'aes-256-cbc';

    private const GCM_IV_LENGTH = 12;

    private const GCM_TAG_LENGTH = 16;

    private const CBC_IV_LENGTH = 16;

    private const PBKDF2_ALGO = 'sha256';

    private const PBKDF2_DEFAULT_ITERATIONS = 100_000;

    private const PBKDF2_MIN_ITERATIONS = 10_000;

    private const AES_KEY_LENGTH = 32; // 256 bits

    private const SALT_LENGTH = 16;

    private const STREAM_CHUNK_SIZE = 64 * 1024; // 64 KB

    private const VERSION = 1;

    private readonly string $appKey;

    public function __construct()
    {
        $rawKey = (string) config('app.key', '');
        $this->appKey = $this->parseKey($rawKey);
    }

    // ═══════════════════════════════════════════════════════════
    //  AES-256-GCM (แนะนำ — Authenticated Encryption)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสด้วย AES-256-GCM
     *
     * GCM ให้ทั้ง confidentiality + integrity + authenticity ในตัว
     *
     * @param  mixed  $data  ข้อมูล (non-string จะถูก json_encode)
     * @param  string|null  $key  กุญแจ 32 bytes (null = ใช้ APP_KEY)
     * @return string  base64-encoded encrypted envelope
     */
    public function encrypt(mixed $data, ?string $key = null): string
    {
        return $this->doEncryptGcm($this->serialize($data), $this->resolveKey($key));
    }

    /**
     * ถอดรหัส AES-256-GCM
     *
     * @param  string  $encrypted  ข้อมูลจาก encrypt()
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decrypt(string $encrypted, ?string $key = null): mixed
    {
        return $this->deserialize(
            $this->doDecryptGcm($encrypted, $this->resolveKey($key)),
        );
    }

    /**
     * เข้ารหัส AES-256-GCM + AAD (Additional Authenticated Data)
     *
     * AAD ไม่ถูกเข้ารหัส แต่ถูก bind เข้ากับ ciphertext →
     * ถ้า AAD ไม่ตรงตอน decrypt จะ fail ทันที
     *
     * Use case: bind ciphertext กับ user_id, request_id, หรือ context อื่น
     * เช่น encrypt ข้อมูลของ user A → user B เอาไปใช้ไม่ได้แม้มี key เดียวกัน
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string  $aad  Additional Authenticated Data (context binding)
     * @param  string|null  $key  กุญแจ (null = APP_KEY)
     * @return string  base64-encoded encrypted envelope
     */
    public function encryptWithAad(mixed $data, string $aad, ?string $key = null): string
    {
        $resolvedKey = $this->resolveKey($key);
        $plaintext = $this->serialize($data);
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_GCM,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM+AAD encrypt ล้มเหลว: ' . openssl_error_string());
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'gcm-aad',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'aad_hash' => hash('sha256', $aad), // เก็บ hash ไว้สำหรับ debug (ไม่ใช้ตอน decrypt)
            'data' => base64_encode($ciphertext),
        ]);
    }

    /**
     * ถอดรหัส AES-256-GCM + AAD
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptWithAad()
     * @param  string  $aad  AAD ต้องตรงกับตอนเข้ารหัส (ถ้าไม่ตรง → fail)
     * @param  string|null  $key  กุญแจ
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptWithAad(string $encrypted, string $aad, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['iv', 'tag', 'data']);

        $resolvedKey = $this->resolveKey($key);
        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $tag = $this->safeBase64Decode($envelope['tag'], 'tag');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_GCM,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-GCM+AAD decrypt ล้มเหลว — AAD ไม่ตรงหรือข้อมูลถูกแก้ไข');
        }

        return $this->deserialize($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  AES-256-CBC + HMAC (สำหรับ compatibility)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสด้วย AES-256-CBC + HMAC-SHA256
     *
     * CBC ไม่มี authentication ในตัว → เพิ่ม HMAC เพื่อป้องกัน padding oracle attack
     * ใช้เมื่อ GCM ไม่รองรับ (legacy system, hardware ที่ไม่มี AES-NI)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $key  กุญแจ 32 bytes (null = ใช้ APP_KEY)
     * @return string  base64-encoded encrypted envelope
     */
    public function encryptCbc(mixed $data, ?string $key = null): string
    {
        $plaintext = $this->serialize($data);
        $resolvedKey = $this->resolveKey($key);
        $iv = random_bytes(self::CBC_IV_LENGTH);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_CBC,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-CBC encrypt ล้มเหลว: ' . openssl_error_string());
        }

        // Encrypt-then-MAC: HMAC ครอบคลุม iv + ciphertext
        $mac = hash_hmac('sha256', $iv . $ciphertext, $resolvedKey, true);

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'cbc',
            'iv' => base64_encode($iv),
            'mac' => base64_encode($mac),
            'data' => base64_encode($ciphertext),
        ]);
    }

    /**
     * ถอดรหัส AES-256-CBC + HMAC
     *
     * Verify MAC ก่อน decrypt เสมอ (Encrypt-then-MAC pattern)
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptCbc()
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptCbc(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['iv', 'mac', 'data']);

        $resolvedKey = $this->resolveKey($key);
        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $mac = $this->safeBase64Decode($envelope['mac'], 'mac');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        // Verify MAC ก่อน (timing-safe)
        $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $resolvedKey, true);

        if (! hash_equals($expectedMac, $mac)) {
            throw new RuntimeException('AES-256-CBC HMAC verification failed — ข้อมูลอาจถูกแก้ไข');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_CBC,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-CBC decrypt ล้มเหลว');
        }

        return $this->deserialize($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  XChaCha20-Poly1305 (modern — via libsodium)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสด้วย XChaCha20-Poly1305 (libsodium)
     *
     * ข้อดีเหนือ AES-GCM:
     *  - 192-bit nonce → ปลอดภัยกว่าสำหรับ random nonce (GCM มีแค่ 96-bit)
     *  - ไม่ต้องการ AES-NI hardware → เร็วทุก CPU
     *  - Misuse-resistant กว่า (nonce collision ไม่ catastrophic เท่า GCM)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $key  กุญแจ 32 bytes (null = APP_KEY, จะถูก derive ด้วย SHA-256)
     * @return string  base64-encoded encrypted envelope
     */
    public function encryptSodium(mixed $data, ?string $key = null): string
    {
        $plaintext = $this->serialize($data);
        $sodiumKey = $this->deriveSodiumKey($key);

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES); // 24 bytes

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '', // AAD
            $nonce,
            $sodiumKey,
        );

        sodium_memzero($sodiumKey);

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'xchacha20',
            'nonce' => base64_encode($nonce),
            'data' => base64_encode($ciphertext),
        ]);
    }

    /**
     * ถอดรหัส XChaCha20-Poly1305
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptSodium()
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptSodium(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['nonce', 'data']);

        $sodiumKey = $this->deriveSodiumKey($key);
        $nonce = $this->safeBase64Decode($envelope['nonce'], 'nonce');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '', // AAD
            $nonce,
            $sodiumKey,
        );

        sodium_memzero($sodiumKey);

        if ($plaintext === false) {
            throw new RuntimeException('XChaCha20-Poly1305 decrypt ล้มเหลว — key ผิดหรือข้อมูลถูกแก้ไข');
        }

        return $this->deserialize($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  Sealed Box (anonymous encryption — libsodium)
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง X25519 key pair สำหรับ Sealed Box
     *
     * @return array{public_key: string, secret_key: string, key_pair: string}
     *         ทุกค่าเป็น base64-encoded
     */
    public function generateSodiumKeyPair(): array
    {
        $keyPair = sodium_crypto_box_keypair();
        $publicKey = sodium_crypto_box_publickey($keyPair);
        $secretKey = sodium_crypto_box_secretkey($keyPair);

        $result = [
            'public_key' => base64_encode($publicKey),
            'secret_key' => base64_encode($secretKey),
            'key_pair' => base64_encode($keyPair),
        ];

        sodium_memzero($secretKey);
        sodium_memzero($keyPair);

        return $result;
    }

    /**
     * Sealed Box Encrypt — ใครก็ encrypt ได้ด้วย public key
     *
     * Use case: client encrypt ข้อมูลส่ง server โดย client ไม่ต้องมี secret key
     * ผู้ส่งไม่สามารถระบุตัวตนได้ (anonymous)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string  $publicKeyBase64  public key (base64-encoded)
     * @return string  base64-encoded sealed box
     */
    public function sealEncrypt(mixed $data, string $publicKeyBase64): string
    {
        $plaintext = $this->serialize($data);
        $publicKey = $this->safeBase64Decode($publicKeyBase64, 'public_key');

        $sealed = sodium_crypto_box_seal($plaintext, $publicKey);

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'sealed',
            'data' => base64_encode($sealed),
        ]);
    }

    /**
     * Sealed Box Decrypt — เฉพาะเจ้าของ key pair เท่านั้นที่ decrypt ได้
     *
     * @param  string  $encrypted  ข้อมูลจาก sealEncrypt()
     * @param  string  $keyPairBase64  key pair (base64-encoded, จาก generateSodiumKeyPair)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function sealDecrypt(string $encrypted, string $keyPairBase64): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['data']);

        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');
        $keyPair = $this->safeBase64Decode($keyPairBase64, 'key_pair');

        $plaintext = sodium_crypto_box_seal_open($ciphertext, $keyPair);

        sodium_memzero($keyPair);

        if ($plaintext === false) {
            throw new RuntimeException('Sealed box decrypt ล้มเหลว — key pair ไม่ตรง');
        }

        return $this->deserialize($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  Password-Based Encryption (PBKDF2 + AES-256-GCM)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสด้วย password (PBKDF2 derive key → AES-256-GCM)
     *
     * PBKDF2 จะ derive 32-byte key จาก password + random salt
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string  $password  รหัสผ่าน
     * @param  int  $iterations  จำนวนรอบ PBKDF2 (default: 100,000)
     * @return string  base64-encoded encrypted envelope
     */
    public function encryptWithPassword(mixed $data, string $password, int $iterations = self::PBKDF2_DEFAULT_ITERATIONS): string
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password is required');
        }

        $plaintext = $this->serialize($data);
        $salt = random_bytes(self::SALT_LENGTH);

        $derivedKey = $this->pbkdf2Derive($password, $salt, $iterations);

        try {
            $iv = random_bytes(self::GCM_IV_LENGTH);
            $tag = '';

            $ciphertext = openssl_encrypt(
                $plaintext,
                self::CIPHER_GCM,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::GCM_TAG_LENGTH,
            );

            if ($ciphertext === false) {
                throw new RuntimeException('Password-based encrypt ล้มเหลว: ' . openssl_error_string());
            }

            return $this->encodeEnvelope([
                'v' => self::VERSION,
                'cipher' => 'pbkdf2-gcm',
                'salt' => base64_encode($salt),
                'iter' => $iterations,
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'data' => base64_encode($ciphertext),
            ]);
        } finally {
            sodium_memzero($derivedKey);
        }
    }

    /**
     * ถอดรหัสที่เข้ารหัสด้วย password
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptWithPassword()
     * @param  string  $password  รหัสผ่าน (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptWithPassword(string $encrypted, string $password): mixed
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password is required');
        }

        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['salt', 'iter', 'iv', 'tag', 'data']);

        $iterations = (int) $envelope['iter'];

        if ($iterations < self::PBKDF2_MIN_ITERATIONS) {
            throw new RuntimeException(
                "PBKDF2 iterations ({$iterations}) ต่ำกว่าขั้นต่ำ (" . self::PBKDF2_MIN_ITERATIONS . ') — อาจถูก tamper',
            );
        }

        $salt = $this->safeBase64Decode($envelope['salt'], 'salt');
        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $tag = $this->safeBase64Decode($envelope['tag'], 'tag');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $derivedKey = $this->pbkdf2Derive($password, $salt, $iterations);

        try {
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::CIPHER_GCM,
                $derivedKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
            );

            if ($plaintext === false) {
                throw new RuntimeException('Password-based decrypt ล้มเหลว — password ผิดหรือข้อมูลถูกแก้ไข');
            }

            return $this->deserialize($plaintext);
        } finally {
            sodium_memzero($derivedKey);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Deterministic Encryption (searchable encrypted fields)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสแบบ deterministic — input เดียวกัน + key เดียวกัน → output เดียวกัน
     *
     * ใช้ SIV-like approach: IV = HMAC(key, plaintext)
     * → ปลอดภัยแม้ IV ซ้ำ เพราะ IV ถูก derive จาก plaintext
     *
     * ⚠️ Trade-off: เปิดเผยว่า plaintext เดียวกันหรือไม่ (equality leak)
     *    ใช้เฉพาะเมื่อต้องการ search/lookup ใน DB เท่านั้น
     *
     * Use case: encrypt email, national ID → ยัง WHERE email = ? ได้
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $key  กุญแจ (null = APP_KEY)
     * @return string  base64-encoded deterministic envelope
     */
    public function encryptDeterministic(mixed $data, ?string $key = null): string
    {
        $plaintext = $this->serialize($data);
        $resolvedKey = $this->resolveKey($key);

        // SIV-like: derive IV จาก HMAC(key, plaintext)
        // → input เดียวกัน = IV เดียวกัน = ciphertext เดียวกัน
        $syntheticIv = substr(
            hash_hmac('sha256', $plaintext, $resolvedKey, true),
            0,
            self::GCM_IV_LENGTH,
        );

        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_GCM,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $syntheticIv,
            $tag,
            '',
            self::GCM_TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Deterministic encrypt ล้มเหลว: ' . openssl_error_string());
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'det-gcm',
            'iv' => base64_encode($syntheticIv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ]);
    }

    /**
     * ถอดรหัส deterministic encryption
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptDeterministic()
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptDeterministic(string $encrypted, ?string $key = null): mixed
    {
        // Decrypt เหมือน GCM ปกติ (format เดียวกัน)
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['iv', 'tag', 'data']);

        $resolvedKey = $this->resolveKey($key);
        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $tag = $this->safeBase64Decode($envelope['tag'], 'tag');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_GCM,
            $resolvedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Deterministic decrypt ล้มเหลว');
        }

        return $this->deserialize($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  Expiring Encryption (encrypt พร้อม TTL)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสพร้อมกำหนดอายุ (TTL)
     *
     * เหมาะสำหรับ: token ชั่วคราว, OTP, reset link, invite code
     *
     * @param  mixed  $data  ข้อมูล
     * @param  int  $ttlSeconds  อายุเป็นวินาที (default: 300 = 5 นาที)
     * @param  string|null  $key  กุญแจ (null = ใช้ APP_KEY)
     * @return string  base64-encoded encrypted envelope
     */
    public function encryptExpiring(mixed $data, int $ttlSeconds = 300, ?string $key = null): string
    {
        $wrapper = [
            '_payload' => $data,
            '_exp' => time() + max(0, $ttlSeconds),
        ];

        return $this->encrypt($wrapper, $key);
    }

    /**
     * ถอดรหัสที่มี expiration — throw ถ้าหมดอายุ
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptExpiring()
     * @param  string|null  $key  กุญแจ
     * @return mixed  ข้อมูลต้นฉบับ
     *
     * @throws RuntimeException เมื่อ token หมดอายุ
     */
    public function decryptExpiring(string $encrypted, ?string $key = null): mixed
    {
        $wrapper = $this->decrypt($encrypted, $key);

        if (! is_array($wrapper) || ! isset($wrapper['_payload'], $wrapper['_exp'])) {
            throw new RuntimeException('Invalid expiring payload format');
        }

        if (time() > (int) $wrapper['_exp']) {
            throw new RuntimeException('Encrypted token has expired');
        }

        return $wrapper['_payload'];
    }

    // ═══════════════════════════════════════════════════════════
    //  URL-Safe Encryption
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสแล้วคืน URL-safe string (ใช้ใน query string, URL path ได้เลย)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $key  กุญแจ (null = APP_KEY)
     * @return string  URL-safe encrypted string
     */
    public function encryptUrlSafe(mixed $data, ?string $key = null): string
    {
        return $this->base64UrlEncode($this->encrypt($data, $key));
    }

    /**
     * ถอดรหัส URL-safe string
     *
     * @param  string  $encrypted  ข้อมูลจาก encryptUrlSafe()
     * @param  string|null  $key  กุญแจ
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptUrlSafe(string $encrypted, ?string $key = null): mixed
    {
        return $this->decrypt($this->base64UrlDecode($encrypted), $key);
    }

    // ═══════════════════════════════════════════════════════════
    //  Stream Encryption (ไฟล์ขนาดใหญ่)
    // ═══════════════════════════════════════════════════════════

    /**
     * Encrypt stream/file ขนาดใหญ่ — ทำงานเป็น chunk ไม่โหลดทั้งไฟล์เข้า memory
     *
     * ใช้ XChaCha20-Poly1305 secretstream (libsodium) ซึ่ง:
     *  - รองรับ streaming natively (ไม่ต้อง chunk + encrypt แยก)
     *  - แต่ละ chunk มี authentication tag ของตัวเอง
     *  - ตรวจลำดับ chunk อัตโนมัติ (ป้องกัน reorder/drop)
     *  - Final chunk มี tag พิเศษ (ป้องกัน truncation)
     *
     * @param  resource  $inputStream  input stream (readable)
     * @param  resource  $outputStream  output stream (writable)
     * @param  string|null  $key  กุญแจ 32 bytes (null = APP_KEY, derive ด้วย SHA-256)
     * @return string  header ที่ต้องเก็บไว้สำหรับ decrypt (base64-encoded)
     */
    public function encryptStream($inputStream, $outputStream, ?string $key = null): string
    {
        $this->assertStream($inputStream, 'input');
        $this->assertStream($outputStream, 'output');

        $sodiumKey = $this->deriveSodiumKey($key);

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($sodiumKey);

            while (! feof($inputStream)) {
                $chunk = fread($inputStream, self::STREAM_CHUNK_SIZE);

                if ($chunk === false) {
                    throw new RuntimeException('อ่าน input stream ล้มเหลว');
                }

                // ถ้าอ่านครบแล้ว (feof) → ใช้ FINAL tag
                $tag = feof($inputStream)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);

                if (fwrite($outputStream, $encrypted) === false) {
                    throw new RuntimeException('เขียน output stream ล้มเหลว');
                }
            }

            return base64_encode($header);
        } finally {
            sodium_memzero($sodiumKey);
        }
    }

    /**
     * Decrypt stream/file ขนาดใหญ่
     *
     * @param  resource  $inputStream  encrypted input stream
     * @param  resource  $outputStream  decrypted output stream
     * @param  string  $headerBase64  header จาก encryptStream() (base64-encoded)
     * @param  string|null  $key  กุญแจ (ต้องตรงกับตอนเข้ารหัส)
     */
    public function decryptStream($inputStream, $outputStream, string $headerBase64, ?string $key = null): void
    {
        $this->assertStream($inputStream, 'input');
        $this->assertStream($outputStream, 'output');

        $sodiumKey = $this->deriveSodiumKey($key);
        $header = $this->safeBase64Decode($headerBase64, 'header');

        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $sodiumKey);

            // แต่ละ chunk ที่อ่านต้องรวม overhead ของ Poly1305 tag (17 bytes)
            $readSize = self::STREAM_CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (! feof($inputStream)) {
                $chunk = fread($inputStream, $readSize);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                [$decrypted, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);

                if ($decrypted === false) {
                    throw new RuntimeException('Stream decrypt ล้มเหลว — ข้อมูลเสียหายหรือ key ผิด');
                }

                if (fwrite($outputStream, $decrypted) === false) {
                    throw new RuntimeException('เขียน output stream ล้มเหลว');
                }

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            sodium_memzero($sodiumKey);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Auto-Detect Decrypt
    // ═══════════════════════════════════════════════════════════

    /**
     * ถอดรหัสโดย auto-detect cipher จาก envelope
     *
     * รองรับ: gcm, gcm-aad (ต้องส่ง $aad), cbc, xchacha20, pbkdf2-gcm (ต้องส่ง $password),
     *         det-gcm, sealed (ต้องส่ง $keyPair)
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัส (จาก method ใดก็ได้)
     * @param  string|null  $key  กุญแจ
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function autoDecrypt(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $cipher = $envelope['cipher'] ?? 'gcm';

        return match ($cipher) {
            'gcm', 'gcm-aad' => $this->decrypt($encrypted, $key),
            'cbc' => $this->decryptCbc($encrypted, $key),
            'xchacha20' => $this->decryptSodium($encrypted, $key),
            'det-gcm' => $this->decryptDeterministic($encrypted, $key),
            'pbkdf2-gcm' => throw new RuntimeException('pbkdf2-gcm ต้องใช้ decryptWithPassword() — ต้องมี password'),
            'sealed' => throw new RuntimeException('sealed ต้องใช้ sealDecrypt() — ต้องมี key pair'),
            default => throw new RuntimeException("Unknown cipher: {$cipher}"),
        };
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Rotation
    // ═══════════════════════════════════════════════════════════

    /**
     * Re-encrypt ด้วย key ใหม่ (key rotation)
     *
     * Decrypt ด้วย key เก่า → Encrypt ด้วย key ใหม่
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัสด้วย key เก่า
     * @param  string  $oldKey  key เก่า
     * @param  string  $newKey  key ใหม่
     * @return string  ข้อมูลที่เข้ารหัสด้วย key ใหม่
     */
    public function reEncrypt(string $encrypted, string $oldKey, string $newKey): string
    {
        $data = $this->autoDecrypt($encrypted, $oldKey);

        return $this->encrypt($data, $newKey);
    }

    /**
     * ลอง decrypt ด้วยหลาย key (สำหรับ rotation period ที่ key เก่ายังใช้ได้)
     *
     * @param  string  $encrypted  ข้อมูลที่เข้ารหัส
     * @param  string[]  $keys  รายการ key ที่ต้องลอง (ลองจาก key แรก → สุดท้าย)
     * @return mixed  ข้อมูลต้นฉบับ
     *
     * @throws RuntimeException ถ้าไม่มี key ไหนถอดรหัสได้
     */
    public function decryptWithFallbackKeys(string $encrypted, array $keys): mixed
    {
        if ($keys === []) {
            throw new InvalidArgumentException('ต้องมีอย่างน้อย 1 key');
        }

        foreach ($keys as $key) {
            try {
                return $this->autoDecrypt($encrypted, $key);
            } catch (RuntimeException) {
                continue;
            }
        }

        throw new RuntimeException('ไม่สามารถถอดรหัสได้ — ไม่มี key ที่ตรง');
    }

    // ═══════════════════════════════════════════════════════════
    //  Utility
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง random AES-256 key (32 bytes, base64-encoded พร้อม prefix)
     *
     * @return string  "base64:xxxx" พร้อมใช้ใน config
     */
    public function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::AES_KEY_LENGTH));
    }

    /**
     * Base64 URL-safe encode (RFC 4648 §5)
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    public function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Base64 URL-safe decode ล้มเหลว');
        }

        return $decoded;
    }

    /**
     * รายการ cipher ที่ OpenSSL รองรับ
     *
     * @return string[]
     */
    public function getAvailableCiphers(): array
    {
        return openssl_get_cipher_methods();
    }

    // ─── Private: Core Encrypt/Decrypt ──────────────────────────

    /**
     * Core GCM encrypt — shared logic
     */
    private function doEncryptGcm(string $plaintext, string $key, string $aad = ''): string
    {
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_GCM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encrypt ล้มเหลว: ' . openssl_error_string());
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'gcm',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ]);
    }

    /**
     * Core GCM decrypt — shared logic
     */
    private function doDecryptGcm(string $encrypted, string $key, string $aad = ''): string
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['iv', 'tag', 'data']);

        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $tag = $this->safeBase64Decode($envelope['tag'], 'tag');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER_GCM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-GCM decrypt ล้มเหลว — key ผิดหรือข้อมูลถูกแก้ไข');
        }

        return $plaintext;
    }

    // ─── Private: Key Management ────────────────────────────────
    // หมายเหตุ: parseKey() มาจาก ParsesEncryptionKey trait

    /**
     * Resolve encryption key
     */
    private function resolveKey(?string $key): string
    {
        $resolved = $key !== null ? $this->parseKey($key) : $this->appKey;

        if ($resolved === '') {
            throw new InvalidArgumentException(
                'Encryption key is required. Set APP_KEY in .env or pass a key explicitly.',
            );
        }

        return $resolved;
    }

    /**
     * Derive 32-byte sodium key จาก APP_KEY (ความยาวอาจไม่ตรง 32 bytes)
     */
    private function deriveSodiumKey(?string $key): string
    {
        $resolved = $this->resolveKey($key);

        // sodium ต้องการ key 32 bytes พอดี → derive ด้วย SHA-256
        if (strlen($resolved) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            return hash('sha256', $resolved, true);
        }

        return $resolved;
    }

    /**
     * PBKDF2 key derivation
     */
    private function pbkdf2Derive(string $password, string $salt, int $iterations): string
    {
        return hash_pbkdf2(
            self::PBKDF2_ALGO,
            $password,
            $salt,
            $iterations,
            self::AES_KEY_LENGTH,
            true,
        );
    }

    // ─── Private: Serialization ─────────────────────────────────

    private function serialize(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function deserialize(string $data): mixed
    {
        $decoded = json_decode($data, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $data;
    }

    // ─── Private: Envelope ──────────────────────────────────────

    private function encodeEnvelope(array $payload): string
    {
        return base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function decodeEnvelope(string $encrypted): array
    {
        $json = base64_decode($encrypted, true);

        if ($json === false) {
            throw new RuntimeException('Envelope base64 decode ล้มเหลว — ข้อมูลไม่ถูกต้อง');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Envelope JSON decode ล้มเหลว — format ไม่ถูกต้อง');
        }

        return $payload;
    }

    private function assertEnvelopeFields(array $envelope, array $required): void
    {
        $missing = array_diff($required, array_keys($envelope));

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Encrypted envelope missing fields: ' . implode(', ', $missing),
            );
        }
    }

    // ─── Private: Utility ───────────────────────────────────────

    private function safeBase64Decode(string $data, string $field): string
    {
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new InvalidArgumentException("Invalid base64 in field: {$field}");
        }

        return $decoded;
    }

    /**
     * @param  resource  $stream
     */
    private function assertStream($stream, string $name): void
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException("{$name} ต้องเป็น valid stream resource");
        }
    }
}
