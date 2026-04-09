<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\SodiumHelperInterface;
use InvalidArgumentException;
use RuntimeException;
use SodiumException;

/**
 * SodiumHelper — ผู้ช่วยจัดการความปลอดภัยขั้นสูงด้วย Libsodium (Production-Grade)
 *
 * ใช้ PHP ext-sodium (libsodium) เป็น Backend หลัก — ประสิทธิภาพสูงสุด ความปลอดภัยระดับสูงสุด
 *
 * ═══════════════════════════════════════════════════════════════
 *  ความสามารถหลัก (Core Capabilities):
 * ═══════════════════════════════════════════════════════════════
 *  1.  Key Management          — generateEncryptionKey, generateSignatureKeyPair,
 *                                generateBoxKeyPair, generateKxKeyPair (ทั้งหมด static)
 *  2.  Symmetric Encryption    — XSalsa20-Poly1305 SecretBox
 *  3.  AEAD                    — XChaCha20-Poly1305-IETF
 *  4.  Asymmetric Encryption   — Box (Authenticated) & Seal (Anonymous) X25519
 *  5.  Digital Signatures      — Ed25519 (detached & multi-part)
 *  6.  Password Hashing        — Argon2id
 *  7.  File/Stream Encryption  — SecretStream XChaCha20-Poly1305 (RAM คงที่)
 *  8.  Hybrid Stream Encryption— Envelope Encryption (ephemeral session key)
 *  9.  Stream Signatures       — BLAKE2b digest + Ed25519 sign
 *  10. Hashing & KDF           — BLAKE2b generic hash, BLAKE2b KDF subkey
 *  11. Key Exchange            — X25519 Diffie-Hellman session keys (static)
 *  12. Large String Helpers    — php://temp stream bridge
 *
 * กุญแจทุกประเภทรับ/คืนเป็น Base64 (SODIUM_BASE64_VARIANT_ORIGINAL)
 * ยกเว้นเมธอดที่มีชื่อลงท้ายว่า UrlSafe ซึ่งใช้ Base64url (ไม่มี padding)
 */
final class SodiumHelper implements SodiumHelperInterface
{
    use DataNormalization, ParsesEncryptionKey;

    /** XChaCha20-Poly1305-IETF — แนะนำ: nonce 192-bit ไม่ซ้ำได้แม้สุ่ม */
    public const AEAD_XCHACHA20POLY1305_IETF = 'xchacha20poly1305_ietf';

    /** ChaCha20-Poly1305-IETF — nonce 96-bit (ต้องระวังการสุ่มซ้ำ) */
    public const AEAD_CHACHA20POLY1305_IETF = 'chacha20poly1305_ietf';

    /** AES-256-GCM — เร็วที่สุดบน CPU ที่มี AES-NI, ต้องตรวจ availability ก่อน */
    public const AEAD_AES256GCM = 'aes256gcm';

    // ─── Argon2id security levels ──────────────────────────────────────────────
    /** INTERACTIVE — เหมาะสำหรับ web login (~64 MB RAM, ~0.3 s) */
    public const string PWHASH_INTERACTIVE = 'interactive';

    /** MODERATE — เหมาะสำหรับข้อมูล sensitive (~256 MB RAM, ~1 s) */
    public const string PWHASH_MODERATE = 'moderate';

    /** SENSITIVE — ความปลอดภัยสูงสุด (~1 GB RAM, ~5 s) */
    public const string PWHASH_SENSITIVE = 'sensitive';

    /** ขนาด Chunk สำหรับ streaming (256 KB) — balance ระหว่าง throughput และ latency */
    private const CHUNK_SIZE = 262144;

    /** Context เริ่มต้นสำหรับ BLAKE2b KDF (ต้องเป็น 8 bytes พอดี) */
    private const KDF_DEFAULT_CONTEXT = 'app_kdf_';

    // ─── Payload format version bytes ─────────────────────────────────────────
    /** Version byte สำหรับ SecretBox payload (v1) */
    private const string FORMAT_SECRETBOX_V1 = "\x01";

    /** Version byte สำหรับ AEAD XChaCha20-Poly1305 payload (v2) */
    private const string FORMAT_AEAD_V1 = "\x02";

    /** กุญแจหลักของแอปพลิเคชัน — raw 32 bytes (ไม่ readonly เพื่อให้ memzero ทำงานได้ใน __destruct) */
    private string $appKey;

    /**
     * @param  string  $appKey  Base64-encoded key ขนาด SODIUM_CRYPTO_SECRETBOX_KEYBYTES (32 bytes)
     *                          ส่งเข้ามาจาก ServiceProvider ผ่าน config('core.base::security.key32')
     *
     * @throws InvalidArgumentException ถ้า key ไม่ถูกขนาด
     */
    public function __construct(string $appKey)
    {
        $this->appKey = $this->resolveKey($appKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);  // 32

        if (empty($this->appKey)) {
            throw new InvalidArgumentException('Key is empty in __construct of SodiumHelper');
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  1. Key Management — static
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง KDF master key สุ่ม (32 bytes)
     *
     * ใช้สำหรับ kdfDerive()
     *
     * @return string base64url encoded key
     */
    public static function generateKdfKey(bool $useBase64 = false): string
    {
        if ($useBase64) {
            return self::encodeb64(\sodium_crypto_kdf_keygen());
        }

        return \sodium_crypto_kdf_keygen();
    }

    /**
     * สร้าง SipHash-2-4 key สุ่ม (16 bytes)
     *
     * ใช้สำหรับ shortHash() เท่านั้น — ห้ามใช้ generateSecretKey() แทน
     * (SODIUM_CRYPTO_SHORTHASH_KEYBYTES = 16 bytes, ต่างจาก secretbox key 32 bytes)
     *
     * @return string base64url encoded key (16 bytes)
     */
    public static function generateShortHashKey(bool $useBase64 = false): string
    {
        if ($useBase64) {
            return self::encodeb64(\sodium_crypto_shorthash_keygen());
        }

        return \sodium_crypto_shorthash_keygen();
    }

    /**
     * SipHash-2-4 Short-input Hash (8 bytes)
     *
     * เหมาะสำหรับ hash table key หรือ MAC ขนาดเล็ก — ไม่เหมาะสำหรับ password หรือ long-term secret
     *
     * @param  string  $message  ข้อความ
     * @param  string  $key  base64url key (16 bytes จาก generateShortHashKey())
     * @return string binary 8-byte hash
     *
     * @throws RuntimeException เมื่อ hash ล้มเหลว
     */
    public static function shortHash(string $message, string $key, bool $useBase64 = false): string
    {
        try {
            $hash = \sodium_crypto_shorthash($message, $key);
            if ($useBase64) {
                return self::encodeb64($hash);
            }

            return $hash;
        } catch (SodiumException $e) {
            throw new RuntimeException('SipHash-2-4 hash ล้มเหลว: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * สร้างกุญแจสำหรับ Symmetric Encryption / AEAD (32 bytes)
     *
     * @return string Base64 url safe
     */
    public static function generateEncryptionKey(bool $useBase64 = false): string
    {
        if ($useBase64) {
            return self::encodeb64(\sodium_crypto_secretbox_keygen());
        }

        return \sodium_crypto_secretbox_keygen();
    }

    /**
     * สร้างคู่กุญแจสำหรับ Digital Signatures (Ed25519)
     *
     * @return array{public: string, secret: string} Base64
     */
    public static function generateSignatureKeyPair(bool $useBase64 = false): array
    {
        $kp = \sodium_crypto_sign_keypair();
        $result = [
            'public' => $useBase64 ? self::encodeb64(\sodium_crypto_sign_publickey($kp)) : \sodium_crypto_sign_publickey($kp),
            'secret' => $useBase64 ? self::encodeb64(\sodium_crypto_sign_secretkey($kp)) : \sodium_crypto_sign_secretkey($kp),
            'keypair' => $useBase64 ? self::encodeb64($kp) : $kp,
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * สร้างคู่กุญแจสำหรับ Asymmetric Box (X25519)
     *  generateSodiumKeyPair()      — สร้าง X25519 key pair
     *
     * @return array{public: string, secret: string} Base64
     */
    public static function generateBoxKeyPair(bool $useBase64 = false): array
    {
        $kp = \sodium_crypto_box_keypair();
        $result = [
            'public' => $useBase64 ? self::encodeb64(\sodium_crypto_box_publickey($kp)) : \sodium_crypto_box_publickey($kp),
            'secret' => $useBase64 ? self::encodeb64(\sodium_crypto_box_secretkey($kp)) : \sodium_crypto_box_secretkey($kp),
            'keypair' => $useBase64 ? self::encodeb64($kp) : $kp,
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * สร้างคู่กุญแจสำหรับ Key Exchange (X25519 kx)
     *
     * @return array{public: string, secret: string, keypair: string} Base64
     */
    public static function generateKxKeyPair(bool $useBase64 = false): array
    {
        $kp = \sodium_crypto_kx_keypair();
        $result = [
            'public' => $useBase64 ? self::encodeb64(\sodium_crypto_kx_publickey($kp)) : \sodium_crypto_kx_publickey($kp),
            'secret' => $useBase64 ? self::encodeb64(\sodium_crypto_kx_secretkey($kp)) : \sodium_crypto_kx_secretkey($kp),
            'keypair' => $useBase64 ? self::encodeb64($kp) : $kp,
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * คืนขนาด nonce (bytes) สำหรับ AEAD algorithm ที่กำหนด
     *
     * @throws InvalidArgumentException เมื่อ algorithm ไม่รองรับ
     */
    public static function aeadNonceBytes(string $algo = self::AEAD_XCHACHA20POLY1305_IETF): int
    {
        return match ($algo) {
            self::AEAD_XCHACHA20POLY1305_IETF => SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES,
            self::AEAD_CHACHA20POLY1305_IETF => SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES,
            self::AEAD_AES256GCM => SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES,
            default => throw new InvalidArgumentException("AEAD algorithm ไม่รองรับ: {$algo}"),
        };
    }

    // ═══════════════════════════════════════════════════════════
    //  11. Key Exchange (X25519) — static
    // ═══════════════════════════════════════════════════════════

    /**
     * คำนวณ session keys ฝั่ง Client (X25519 Diffie-Hellman)
     * Client session keys หลัง X25519 DH — คืน Base64
     *
     * @return array{rx: string, tx: string}
     */
    public static function kxClientKeys(string $clientKeyPairb64, string $serverPublicKeyb64, bool $isBase64 = false, bool $urlSafe = false): array
    {
        $clientKeyPair = self::decodeb64($clientKeyPairb64);
        $serverPublicKey = self::decodeb64($serverPublicKeyb64);
        [$rx, $tx] = \sodium_crypto_kx_client_session_keys($clientKeyPair, $serverPublicKey);
        \sodium_memzero($clientKeyPair);
        if ($isBase64) {
            if ($urlSafe) {
                $rx = self::encodeb64UrlSafe($rx);
                $tx = self::encodeb64UrlSafe($tx);
            } else {
                $rx = self::encodeb64($rx);
                $tx = self::encodeb64($tx);
            }
        }
        $result = ['rx' => $rx, 'tx' => $tx];
        \sodium_memzero($rx);
        \sodium_memzero($tx);

        return $result;
    }

    /**
     * คำนวณ session keys ฝั่ง Server (X25519 Diffie-Hellman)
     * Server session keys หลัง X25519 DH — คืน Base64
     *
     * @return array{rx: string, tx: string}
     */
    public static function kxServerKeys(string $serverKeyPairb64, string $clientPublicKeyb64, bool $isBase64 = false, bool $urlSafe = false): array
    {
        $serverKeyPair = self::decodeb64($serverKeyPairb64);
        $clientPublicKey = self::decodeb64($clientPublicKeyb64);
        [$rx, $tx] = \sodium_crypto_kx_server_session_keys($serverKeyPair, $clientPublicKey);
        \sodium_memzero($serverKeyPair);
        if ($isBase64) {
            if ($urlSafe) {
                $rx = self::encodeb64UrlSafe($rx);
                $tx = self::encodeb64UrlSafe($tx);
            } else {
                $rx = self::encodeb64($rx);
                $tx = self::encodeb64($tx);
            }
        }
        $result = ['rx' => $rx, 'tx' => $tx];
        \sodium_memzero($rx);
        \sodium_memzero($tx);

        return $result;
    }

    /**
     * คำนวณ raw X25519 ECDH shared secret (scalar multiplication)
     *
     * ⚠️  output เป็น raw 32 bytes — ต้องผ่าน KDF (hash() / kdfDerive()) ก่อนนำไปใช้เป็น key
     *
     * @return string binary shared secret (32 bytes)
     *
     * @throws RuntimeException เมื่อ ECDH ล้มเหลว
     */
    public static function ecdhSharedSecret(string $ourSkb64, string $theirPkb64): string
    {
        try {
            $ourSk = self::decodeb64($ourSkb64);
            $theirPk = self::decodeb64($theirPkb64);

            return \sodium_crypto_scalarmult(
                $ourSk,
                $theirPk,
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('X25519 ECDH ล้มเหลว: '.$e->getMessage(), 0, $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Utilities — static
    //  encode() / decode() มาจาก ParsesEncryptionKey trait
    //  @see \Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey
    // ═══════════════════════════════════════════════════════════

    public static function equals(string $a, string $b): bool
    {
        return \hash_equals($a, $b);
    }

    public static function memzero(string &$secret): void
    {
        \sodium_memzero($secret);
    }

    /**
     * เซ็นพร้อมรวม signature ไว้ใน message (combined format — 64 + N bytes)
     *
     * ใช้คู่กับ openSigned() เสมอ
     *
     * @param  string  $message  ข้อความที่ต้องการเซ็น
     * @return string binary signed message (signature prepended)
     *
     * @throws RuntimeException เมื่อ sign ล้มเหลว
     */
    public static function signCombined(string $message, string $signingSecretKeyb64): string
    {
        try {
            $signingSecretKey = self::decodeb64($signingSecretKeyb64);

            return \sodium_crypto_sign($message, $signingSecretKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 signCombined ล้มเหลว: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * ตรวจสอบและแยก message ออกจาก signed combined message
     *
     * @param  string  $signed  binary signed message จาก signCombined()
     * @return string message ต้นฉบับ
     *
     * @throws RuntimeException เมื่อ signature ไม่ถูกต้อง
     */
    public static function openSigned(string $signed, string $signingPublicKeyb64): string
    {
        try {
            $signingPublicKey = self::decodeb64($signingPublicKeyb64);
            $result = \sodium_crypto_sign_open($signed, $signingPublicKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 openSigned ล้มเหลว: '.$e->getMessage(), 0, $e);
        }

        if ($result === false) {
            throw new RuntimeException('Ed25519 signature ไม่ถูกต้อง — message อาจถูกดัดแปลง');
        }

        return $result;
    }

    /**
     * Constant-time comparison ของ binary strings สองตัว (ป้องกัน timing attack)
     *
     * คืน 0 ถ้าเท่ากัน, -1 ถ้า a < b, 1 ถ้า a > b (by byte magnitude)
     * ต้องยาวเท่ากัน มิฉะนั้น sodium จะ throw SodiumException
     *
     * @throws RuntimeException เมื่อ strings มีความยาวต่างกัน
     */
    public static function compare(string $a, string $b): int
    {
        try {
            return \sodium_compare($a, $b);
        } catch (SodiumException $e) {
            throw new RuntimeException('sodium_compare ล้มเหลว: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * ตรวจสอบว่า CPU รองรับ AES-256-GCM (ต้องมี hardware AES-NI)
     *
     * ใช้ตรวจก่อนเรียก aeadEncrypt/aeadDecrypt ด้วย AEAD_AES256GCM
     *
     * ตัวอย่าง:
     * ```php
     * $algo = CryptHelper::isAes256GcmAvailable()
     *     ? CryptHelper::AEAD_AES256GCM
     *     : CryptHelper::AEAD_XCHACHA20POLY1305_IETF;
     * ```
     */
    public static function isAes256GcmAvailable(): bool
    {
        return \sodium_crypto_aead_aes256gcm_is_available();
    }

    /**
     * ตรวจสอบว่า PHP sodium extension โหลดอยู่และพร้อมใช้งาน
     *
     * @throws RuntimeException เมื่อ extension ไม่พร้อมใช้งาน
     */
    public static function assertSodium(): void
    {
        if (! \extension_loaded('sodium')) {
            throw new RuntimeException(
                'PHP sodium extension ไม่ได้โหลด — กรุณาเปิดใช้งาน ext-sodium ใน php.ini',
            );
        }
    }

    /**
     * ตรวจสอบว่า CPU รองรับ AES-256-GCM — throw ถ้าไม่รองรับ, null ถ้ารองรับ
     *
     * ใช้ภายใน aeadEncrypt/aeadDecrypt ผ่าน null-coalescing pattern:
     *   static::requireAes256Gcm() ?? sodium_crypto_aead_aes256gcm_encrypt(...)
     * → ถ้า null (available) ใช้ด้านขวา; ถ้า throw ส่งต่อไปยัง caller
     *
     * @throws RuntimeException เมื่อ CPU ไม่มี AES-NI
     */
    private static function requireAes256Gcm(): null
    {
        if (! \sodium_crypto_aead_aes256gcm_is_available()) {
            throw new RuntimeException(
                'AES-256-GCM ไม่รองรับบน CPU นี้ — ใช้ AEAD_XCHACHA20POLY1305_IETF แทน',
            );
        }

        return null;
    }

    /**
     * AEAD Encrypt — เข้ารหัสพร้อม Additional Authenticated Data (AAD)
     *
     * แนะนำ: ใช้ AEAD_XCHACHA20POLY1305_IETF เป็น default
     *  - nonce 192-bit → ปลอดภัยสุ่มได้ไม่ต้องกังวลซ้ำ
     *  - เร็วกว่า AES-256-GCM บน CPU ที่ไม่มี AES-NI
     *
     * ใช้ AEAD_AES256GCM บน CPU ที่มี AES-NI ต้องตรวจก่อนด้วย
     * sodium_crypto_aead_aes256gcm_is_available()
     *
     * @param  string  $message  plaintext
     * @param  string  $additionalData  AAD — bind header/context กับ ciphertext (ไม่เข้ารหัส แต่ auth)
     * @param  string  $nonce  binary nonce (ขนาดดูจาก aeadNonceBytes())
     * @param  string  $algo  AEAD algorithm constant
     * @return string binary ciphertext + tag
     *
     * @throws InvalidArgumentException เมื่อ algorithm ไม่รองรับ
     * @throws RuntimeException เมื่อ encrypt ล้มเหลวหรือ CPU ไม่รองรับ AES
     */
    public function aeadEncrypt(
        string $message,
        string $additionalData,
        string $nonce,
        string $keyb64,
        string $algo = self::AEAD_XCHACHA20POLY1305_IETF,
    ): string {
        $rawKey = $this->decodeb64($keyb64);
        $message = $this->normalizeData($message);

        try {
            return match ($algo) {
                self::AEAD_XCHACHA20POLY1305_IETF => \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    $message,
                    $additionalData,
                    $nonce,
                    $rawKey,
                ),
                self::AEAD_CHACHA20POLY1305_IETF => \sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
                    $message,
                    $additionalData,
                    $nonce,
                    $rawKey,
                ),
                self::AEAD_AES256GCM => self::requireAes256Gcm() ??
                    \sodium_crypto_aead_aes256gcm_encrypt($message, $additionalData, $nonce, $rawKey),
                default => throw new InvalidArgumentException("AEAD algorithm ไม่รองรับ: {$algo}"),
            };
        } catch (SodiumException $e) {
            throw new RuntimeException("AEAD ({$algo}) encrypt ล้มเหลว: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * AEAD Decrypt — ถอดรหัสและตรวจสอบ AAD + MAC
     *
     * AAD ต้องตรงกับที่ใช้ตอน encrypt ทุกไบต์ มิฉะนั้น authentication จะล้มเหลว
     *
     * @param  string  $ciphertext  binary ciphertext + tag จาก aeadEncrypt()
     * @param  string  $additionalData  AAD เดียวกับที่ใช้ตอน encrypt
     * @param  string  $nonce  binary nonce เดียวกับที่ใช้ตอน encrypt
     * @param  string  $key  base64url key (32 bytes)
     * @param  string  $algo  algorithm เดียวกับที่ใช้ encrypt
     * @return string plaintext
     *
     * @throws InvalidArgumentException เมื่อ algorithm ไม่รองรับ
     * @throws RuntimeException เมื่อ authentication ล้มเหลวหรือถอดรหัสไม่ได้
     */
    public function aeadDecrypt(
        string $ciphertext,
        string $additionalData,
        string $nonce,
        string $key,
        string $algo = self::AEAD_XCHACHA20POLY1305_IETF,
    ): string {
        $rawKey = $this->decodeb64($key);

        try {
            $result = match ($algo) {
                self::AEAD_XCHACHA20POLY1305_IETF => \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                    $ciphertext,
                    $additionalData,
                    $nonce,
                    $rawKey,
                ),
                self::AEAD_CHACHA20POLY1305_IETF => \sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
                    $ciphertext,
                    $additionalData,
                    $nonce,
                    $rawKey,
                ),
                self::AEAD_AES256GCM => self::requireAes256Gcm() ??
                    \sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $additionalData, $nonce, $rawKey),
                default => throw new InvalidArgumentException("AEAD algorithm ไม่รองรับ: {$algo}"),
            };
        } catch (SodiumException $e) {
            throw new RuntimeException("AEAD ({$algo}) decrypt ล้มเหลว: ".$e->getMessage(), 0, $e);
        }

        if ($result === false) {
            throw new RuntimeException(
                "AEAD ({$algo}) authentication ล้มเหลว — ciphertext หรือ AAD อาจถูกดัดแปลง",
            );
        }

        return $result;
    }

    /**
     * ล้างข้อมูลกุญแจออกจากหน่วยความจำเมื่อทำลาย Object
     */
    public function __destruct()
    {
        self::memzero($this->appKey);
    }

    // ═══════════════════════════════════════════════════════════
    //  2. Symmetric Encryption (SecretBox — XSalsa20-Poly1305)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัส Symmetric — คืน Base64
     */
    public function encrypt(mixed $message, ?string $key32b64 = null, bool $returnBase64 = true, bool $urlSafe = false): string
    {
        return $this->secretboxEncryptInternal($message, $key32b64, $returnBase64, $urlSafe);
    }

    /**
     * ถอดรหัส Symmetric จาก Base64
     */
    public function decrypt(string $payloadb64, ?string $key32b64 = null, bool $isBase64Input = true, bool $urlSafe = false): mixed
    {
        $rawkey = empty($key32b64) ? $this->appKey : $this->resolveKey($key32b64, 32);
        if (empty($rawkey)) {
            throw new InvalidArgumentException('Encryption key is not configured.');
        }
        if ($isBase64Input) {
            $payloadb64 = $urlSafe ? self::decodeb64UrlSafe($payloadb64) : self::decodeb64($payloadb64);
        }

        /* $binary = $isBase64
            ? ($urlSafe ? self::decodeb64UrlSafe($ciphertext) : self::decodeb64($ciphertext))
            : $ciphertext; */

        return $this->decryptRaw($payloadb64, $rawkey);
    }

    /**
     * Key Rotation — ถอดรหัสด้วยกุญแจเดิมและเข้ารหัสใหม่ด้วยกุญแจปัจจุบัน
     *
     * @return string ข้อมูลใหม่ที่เข้ารหัสด้วยกุญแจปัจจุบัน (Base64)
     */
    public function rotateKey(string $payload, string $oldKey): string
    {
        $plaintext = $this->decrypt($payload, $oldKey);
        $newCiphertext = $this->encrypt($plaintext);

        self::memzero($plaintext);

        return $newCiphertext;
    }

    // ═══════════════════════════════════════════════════════════
    //  3. AEAD (XChaCha20-Poly1305-IETF)
    // ═══════════════════════════════════════════════════════════

    /**
     * AEAD Encrypt — คืน Base64
     */
    public function encryptAead(string $message, string $aad = '', ?string $keyb64 = null, bool $returnBase64 = true): string
    {
        $key = $keyb64 !== null ? $this->decodeb64($keyb64) : $this->appKey;
        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;  // 24 bytes
        //
        $nonce = \random_bytes($nonceLen);

        // format v1: VERSION(1) + nonce(24) + ciphertext+tag
        $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($message, $aad, $nonce, $key);
        if ($ciphertext === false) {
            throw new RuntimeException('AEAD เข้ารหัสล้มเหลว');
        }

        $data = self::FORMAT_AEAD_V1.$nonce.$ciphertext;

        if ($returnBase64) {
            $data = self::encodeb64($data);
        }

        return $data;
    }

    /**
     * AEAD Decrypt จาก Base64
     */
    public function decryptAead(string $decoded, string $aad = '', ?string $keyb64 = null, bool $isBase64Input = true): mixed
    {
        $key = $keyb64 !== null ? $this->decodeb64($keyb64) : $this->appKey;
        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; // 24
        $minLenV1 = 1 + $nonceLen + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES; // 1+24+16 = 41
        $minLenLeg = $nonceLen + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;     // 24+16 = 40
        if ($isBase64Input) {
            $decoded = self::decodeb64($decoded);
            if ($decoded === false) {
                throw new RuntimeException('AEAD decrypt ล้มเหลว: input ไม่ใช่ Base64 ที่ถูกต้อง');
            }
        }

        // ─── Versioned format v1 ──────────────────────────────────────
        if (\strlen($decoded) >= $minLenV1 && $decoded[0] === self::FORMAT_AEAD_V1) {
            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                \substr($decoded, 1 + $nonceLen),    // ciphertext+tag
                $aad,
                \substr($decoded, 1, $nonceLen),     // nonce
                $key,
            );

            if ($plaintext === false) {
                // ✅ ไม่ fallthrough — fail ทันทีเมื่อรู้ว่าเป็น v1
                throw new RuntimeException('AEAD v1 ถอดรหัสล้มเหลว: key หรือ AAD ไม่ถูกต้อง');
            }

            return $this->deserializeData($plaintext);
        }

        // ─── Legacy format (backward compatible) ─────────────────────
        if (\strlen($decoded) < $minLenLeg) {
            throw new RuntimeException(
                'AEAD payload เล็กเกินไป (ต้องการอย่างน้อย '.$minLenLeg.' bytes)',
            );
        }

        // ✅ ตรวจก่อนว่าไม่ใช่ unknown version byte
        // byte แรกที่ >= \x01 แต่ไม่ใช่ FORMAT_AEAD_V1 = version ที่ไม่รู้จัก
        if ($decoded[0] >= "\x01" && $decoded[0] !== self::FORMAT_AEAD_V1) {
            throw new RuntimeException(
                'AEAD: ไม่รองรับ format version 0x'.bin2hex($decoded[0]),
            );
        }

        $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            \substr($decoded, $nonceLen),
            $aad,
            \substr($decoded, 0, $nonceLen),
            $key,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AEAD ถอดรหัสล้มเหลว: ตรวจสอบ key หรือ AAD');
        }

        return $this->deserializeData($plaintext);
    }

    // ═══════════════════════════════════════════════════════════
    //  4. Asymmetric Encryption (Box & Seal — X25519)
    // ═══════════════════════════════════════════════════════════

    /**
     * Box Encrypt (Authenticated) — คืน Base64
     */
    public function box(string $message, string $recipientPublicKey, string $senderSecretKey): string
    {
        return $this->boxInternal($message, $recipientPublicKey, $senderSecretKey, false);
    }

    /**
     * Box Encrypt (Authenticated) — คืน Base64url
     */
    public function boxUrlSafe(string $message, string $recipientPublicKey, string $senderSecretKey): string
    {
        return $this->boxInternal($message, $recipientPublicKey, $senderSecretKey, true);
    }

    /**
     * Box Decrypt จาก Base64
     */
    public function boxOpen(string $payloadBase64, string $senderPublicKey, string $recipientSecretKey): string
    {
        return $this->boxOpenRaw($payloadBase64, $senderPublicKey, $recipientSecretKey);
    }

    /**
     * Box Decrypt จาก Base64url
     */
    public function boxOpenUrlSafe(string $payloadUrlSafe, string $senderPublicKey, string $recipientSecretKey): string
    {
        return $this->boxOpenRaw(self::decodeb64UrlSafe($payloadUrlSafe), $senderPublicKey, $recipientSecretKey);
    }

    /**
     * Sealed Box Encrypt (Anonymous) — คืน Base64
     */
    public function seal(string $message, string $recipientPublicKey): string
    {
        return self::encodeb64(\sodium_crypto_box_seal($message, self::decodeb64($recipientPublicKey)));
    }

    /**
     * Sealed Box Encrypt (Anonymous) — คืน Base64url
     */
    public function sealUrlSafe(string $message, string $recipientPublicKey): string
    {
        return self::encodeb64UrlSafe(\sodium_crypto_box_seal($message, self::decodeb64($recipientPublicKey)));
    }

    /**
     * Sealed Box Decrypt จาก Base64
     */
    public function sealOpen(string $payloadBase64, string $recipientPublicKey, string $recipientSecretKey): string
    {
        return $this->sealOpenRaw(self::decodeb64($payloadBase64), $recipientPublicKey, $recipientSecretKey);
    }

    /**
     * Sealed Box Decrypt จาก Base64url
     */
    public function sealOpenUrlSafe(string $payloadUrlSafe, string $recipientPublicKey, string $recipientSecretKey): string
    {
        return $this->sealOpenRaw(self::decodeb64UrlSafe($payloadUrlSafe), $recipientPublicKey, $recipientSecretKey);
    }

    // ═══════════════════════════════════════════════════════════
    //  5. Digital Signatures (Ed25519)
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้างลายเซ็น Detached — คืน Base64
     */
    public function sign(string $message, string $secretKeyBase64, bool $isBase64 = false, bool $urlSafe = false): string
    {
        $sk = self::decodeb64($secretKeyBase64);
        $sig = \sodium_crypto_sign_detached($message, $sk);
        \sodium_memzero($sk);

        //
        return $this->maybeBase64($sig, $isBase64, $urlSafe);
    }

    /**
     * ตรวจสอบลายเซ็น Detached จาก Base64
     */
    public function verify(string $signature, string $message, string $publicKeyBase64): bool
    {
        $pk = self::decodeb64($publicKeyBase64);

        return \sodium_crypto_sign_verify_detached(
            $signature,
            $message,
            $pk,
        );
    }

    // ─── Multi-part Signatures (Ed25519ph) ───────────────────────────

    public function signInit(): string
    {
        return \sodium_crypto_sign_init();
    }

    public function signUpdate(string &$state, string $chunk): void
    {
        \sodium_crypto_sign_update($state, $chunk);
    }

    /**
     * สรุปลายเซ็น Multi-part — คืน Base64
     */
    public function signFinalCreate(string $state, string $secretKeyBase64): string
    {
        $sk = self::decodeb64($secretKeyBase64);
        $sig = \sodium_crypto_sign_final_create($state, $sk);
        \sodium_memzero($sk);

        return self::encodeb64($sig);
    }

    /**
     * ตรวจสอบลายเซ็น Multi-part จาก Base64
     */
    public function signFinalVerify(string $state, string $signatureBase64, string $publicKeyBase64): bool
    {
        return \sodium_crypto_sign_final_verify(
            $state,
            self::decodeb64($signatureBase64),
            self::decodeb64($publicKeyBase64),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  6. Password Hashing (Argon2id)
    // ═══════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════
    //  7. File/Stream Encryption (SecretStream XChaCha20-Poly1305)
    //     RAM คงที่ = CHUNK_SIZE ไม่ว่าไฟล์จะใหญ่แค่ไหน
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัสไฟล์
     */
    public function encryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void
    {
        $key = $this->resolveKey($keyBase64);
        $in = $this->openFile($sourcePath, 'rb');
        $out = $this->openFile($destPath, 'wb');
        try {
            $this->encryptStreamRaw($in, $out, $key);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * ถอดรหัสไฟล์
     */
    public function decryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void
    {
        $key = $this->resolveKey($keyBase64);
        $in = $this->openFile($sourcePath, 'rb');
        $out = $this->openFile($destPath, 'wb');
        try {
            $this->decryptStreamRaw($in, $out, $key);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * เข้ารหัส Stream (open handles) ด้วย Base64 key
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function encryptStream($inputStream, $outputStream, string $keyBase64): void
    {
        $this->encryptStreamRaw($inputStream, $outputStream, self::b64Decode($keyBase64));
    }

    /**
     * ถอดรหัส Stream (open handles) ด้วย Base64 key
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function decryptStream($inputStream, $outputStream, string $keyBase64): void
    {
        $this->decryptStreamRaw($inputStream, $outputStream, self::b64Decode($keyBase64));
    }

    // ═══════════════════════════════════════════════════════════
    //  8. Hybrid Stream Encryption (Envelope Encryption)
    //     Ephemeral session key — ปิดผนึกด้วย Public Key ผู้รับ
    // ═══════════════════════════════════════════════════════════

    /**
     * ปิดผนึก Stream ด้วย Public Key ของผู้รับ
     *
     * Format: pack('v', sealedKeyLen) + sealedKey + secretstream
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function sealStream($inputStream, $outputStream, string $recipientPublicKey): void
    {
        $sessionKey = \sodium_crypto_secretstream_xchacha20poly1305_keygen();
        $sealedKey = \sodium_crypto_box_seal($sessionKey, self::b64Decode($recipientPublicKey));

        \fwrite($outputStream, \pack('v', \strlen($sealedKey)));
        \fwrite($outputStream, $sealedKey);

        $this->encryptStreamRaw($inputStream, $outputStream, $sessionKey);
        \sodium_memzero($sessionKey);
    }

    /**
     * เปิดผนึก Stream ด้วย Secret Key ของผู้รับ
     *
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function openSealedStream(
        $inputStream,
        $outputStream,
        string $recipientPublicKey,
        string $recipientSecretKey,
    ): void {
        $sizeData = \fread($inputStream, 2);
        if ($sizeData === false || \strlen($sizeData) !== 2) {
            throw new RuntimeException('Stream ผิดรูปแบบ: ไม่มี envelope header');
        }
        $sealedKeySize = \unpack('v', $sizeData)[1];

        $sealedKey = \fread($inputStream, $sealedKeySize);
        if ($sealedKey === false || \strlen($sealedKey) !== $sealedKeySize) {
            throw new RuntimeException('Stream ผิดรูปแบบ: envelope ไม่ครบ');
        }

        $pk = self::decodeb64($recipientPublicKey);
        $sk = self::decodeb64($recipientSecretKey);
        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $sessionKey = \sodium_crypto_box_seal_open($sealedKey, $kp);
        \sodium_memzero($kp);

        if ($sessionKey === false) {
            throw new RuntimeException('ไม่สามารถเปิด envelope ได้: กุญแจไม่ถูกต้องหรือข้อมูลถูกดัดแปลง');
        }

        $this->decryptStreamRaw($inputStream, $outputStream, $sessionKey);
        \sodium_memzero($sessionKey);
    }

    // ═══════════════════════════════════════════════════════════
    //  9. Stream Signatures (BLAKE2b digest + Ed25519)
    //     RAM คงที่ — เหมาะสำหรับไฟล์ขนาดใหญ่
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้างลายเซ็นสำหรับ Stream — คืน Base64
     *
     * กลไก: BLAKE2b incremental hash ทั้ง stream → Ed25519 sign ผลลัพธ์
     *
     * @param  resource  $inputStream
     */
    public function signStream($inputStream, string $secretKeyBase64): string
    {
        $sk = self::decodeb64($secretKeyBase64);
        $sig = \sodium_crypto_sign_detached($this->hashStreamRaw($inputStream), $sk);
        \sodium_memzero($sk);

        return self::encodeb64($sig);
    }

    /**
     * ตรวจสอบลายเซ็น Stream
     *
     * @param  resource  $inputStream
     */
    public function verifyStreamSignature($inputStream, string $signatureBase64, string $publicKeyBase64): bool
    {
        return \sodium_crypto_sign_verify_detached(
            self::decodeb64($signatureBase64),
            $this->hashStreamRaw($inputStream),
            self::decodeb64($publicKeyBase64),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  10. Hashing & KDF (BLAKE2b)
    // ═══════════════════════════════════════════════════════════

    /**
     * BLAKE2b Generic Hash — คืน hex
     */
    public function hash(string $message, string $keyBase64 = '', int $length = SODIUM_CRYPTO_GENERICHASH_BYTES): string
    {
        $key = $keyBase64 !== '' ? self::decodeb64($keyBase64) : '';

        return \sodium_bin2hex(\sodium_crypto_generichash($message, $key, $length));
    }

    /**
     * BLAKE2b KDF — derive sub-key จาก master key — คืน Base64
     */
    public function kdfDerive(string $masterKeyBase64, int $subkeyId, string $context = self::KDF_DEFAULT_CONTEXT, int $length = 32): string
    {
        $key = self::decodeb64($masterKeyBase64);
        $ctx = \str_pad(\substr($context, 0, 8), 8, "\0");

        return self::encodeb64(\sodium_crypto_kdf_derive_from_key($length, $subkeyId, $ctx, $key));
    }

    // ═══════════════════════════════════════════════════════════
    //  12. Large String Helpers (php://temp bridge)
    // ═══════════════════════════════════════════════════════════

    /**
     * เข้ารหัส String ขนาดใหญ่ผ่าน temp stream — คืน Base64
     */
    public function encryptHugeString(string $hugeText, string $keyBase64): string
    {
        $key = self::decodeb64($keyBase64);
        $in = \fopen('php://memory', 'r+b');
        $out = \fopen('php://memory', 'r+b');
        try {
            \fwrite($in, $hugeText);
            \rewind($in);
            $this->encryptStreamRaw($in, $out, $key);
            \rewind($out);

            return self::encodeb64(\stream_get_contents($out));
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * ถอดรหัส String ขนาดใหญ่จาก Base64
     */
    public function decryptHugeString(string $encryptedBase64, string $keyBase64): string
    {
        $key = self::decodeb64($keyBase64);
        $in = \fopen('php://memory', 'r+b');
        $out = \fopen('php://memory', 'r+b');
        try {
            \fwrite($in, self::decodeb64($encryptedBase64));
            \rewind($in);
            $this->decryptStreamRaw($in, $out, $key);
            \rewind($out);

            return \stream_get_contents($out);
        } finally {
            \fclose($in);
            \fclose($out);
        }
    }

    /**
     * Sealed Box Decrypt — โดยใช้ Keypair (Base64) ตรงๆ
     */
    public function sealOpenWithKeyPair(string $payloadBase64, string $keyPairBase64): string
    {
        $binary = self::decodeb64($payloadBase64);
        $kp = self::decodeb64($keyPairBase64);

        $plaintext = \sodium_crypto_box_seal_open($binary, $kp);
        \sodium_memzero($kp);

        if ($plaintext === false) {
            throw new RuntimeException('SealedBox ถอดรหัสล้มเหลว: กุญแจไม่ถูกต้องหรือข้อมูลถูกดัดแปลง');
        }

        return $plaintext;
    }

    public function boxInternal(string $message, string $recipientPublicKey, string $senderSecretKey, bool $isBase64 = false, bool $urlSafe = false): string
    {
        $pk = self::decodeb64($recipientPublicKey);
        $sk = self::decodeb64($senderSecretKey);
        $nonce = \random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);

        $data = $nonce.\sodium_crypto_box($message, $nonce, $kp);
        \sodium_memzero($kp);
        \sodium_memzero($sk);

        return $this->maybeBase64($data, $isBase64, $urlSafe);
    }

    protected function getAppKey(): string
    {
        return $this->appKey;
    }

    // เข้ารหัสแบบสมมาตร  ข้อมูลจะถูกเข้ารหัสด้วยกุญแจเดียวกัน
    //  ใช้กุญแจ  Secretbox (XSalsa20-Poly1305)
    private function secretboxEncryptInternal(string $message, ?string $keyBase64, bool $returnBase64 = true, bool $urlSafe = false): string
    {
        $key = empty($keyBase64) ? $this->appKey : $this->resolveKey($keyBase64, 32);
        if (empty($key)) {
            throw new InvalidArgumentException('Encryption key is not configured.');
        }  // key 32 ไบท์
        $nonce = \random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        // format v1: VERSION(1) + nonce(24) + ciphertext+MAC
        $data = self::FORMAT_SECRETBOX_V1.$nonce.\sodium_crypto_secretbox($message, $nonce, $key);
        if ($returnBase64) {
            return $urlSafe ? self::encodeb64UrlSafe($data) : self::encodeb64($data);
        }

        return $data;
    }

    // ═══════════════════════════════════════════════════════════
    //  Private Helpers
    // ═══════════════════════════════════════════════════════════

    private function decryptRaw(string $binary, string $key32): mixed
    {
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        // ─── Versioned format v1: VERSION(1) + nonce(24) + ciphertext ────────
        if (
            \strlen($binary) > 1 + $nonceLen
            && $binary[0] === self::FORMAT_SECRETBOX_V1
        ) {
            $plaintext = \sodium_crypto_secretbox_open(
                \substr($binary, 1 + $nonceLen),
                \substr($binary, 1, $nonceLen),
                $key32,
            );
            if ($plaintext !== false) {
                return $this->deserializeData($plaintext);
                // return $this->maybeBase64($plaintext, $isBase64, $urlSafe);
            }
        }

        // ─── Legacy format: nonce(24) + ciphertext (backward compatible) ─────
        if (\strlen($binary) < $nonceLen) {
            throw new RuntimeException('Payload มีขนาดเล็กเกินไป');
        }
        $plaintext = \sodium_crypto_secretbox_open(
            \substr($binary, $nonceLen),
            \substr($binary, 0, $nonceLen),
            $key32,
        );
        if ($plaintext === false) {
            throw new RuntimeException('ถอดรหัสล้มเหลว: กุญแจไม่ถูกต้องหรือข้อมูลถูกดัดแปลง');
        }

        return $this->deserializeData($plaintext);

        // return $this->maybeBase64($plaintext, $isBase64, $urlSafe);
    }

    // ═══════════════════════════════════════════════════════════
    //  Private Helpers
    // ═══════════════════════════════════════════════════════════
    // ถอดรหัสแบบ Asymmetric จาก Base64

    private function boxOpenRaw(string $binary, string $senderPublicKeyb64, string $recipientSecretKeyb64): string
    {
        $pk = self::decodeb64($senderPublicKeyb64);
        $sk = self::decodeb64($recipientSecretKeyb64);
        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $plaintext = \sodium_crypto_box_open(
            \substr($binary, SODIUM_CRYPTO_BOX_NONCEBYTES),
            \substr($binary, 0, SODIUM_CRYPTO_BOX_NONCEBYTES),
            $kp,
        );
        \sodium_memzero($kp);

        if ($plaintext === false) {
            throw new RuntimeException('Box ถอดรหัสล้มเหลว: ข้อมูลอาจถูกดัดแปลง');
        }

        return $plaintext;
    }

    private function sealOpenRaw(string $binary, string $recipientPublicKeyb64, string $recipientSecretKeyb64): string
    {
        $pk = self::decodeb64($recipientPublicKeyb64);
        $sk = self::decodeb64($recipientSecretKeyb64);
        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $plaintext = \sodium_crypto_box_seal_open($binary, $kp);
        \sodium_memzero($kp);

        if ($plaintext === false) {
            throw new RuntimeException('SealedBox ถอดรหัสล้มเหลว: กุญแจไม่ถูกต้อง');
        }

        return $plaintext;
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function encryptStreamRaw($in, $out, string $rawKey): void
    {
        [$state, $header] = \sodium_crypto_secretstream_xchacha20poly1305_init_push($rawKey);
        \fwrite($out, $header);

        while (! \feof($in)) {
            $chunk = \fread($in, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') {
                break;
            }
            \fwrite($out, \sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                $chunk,
                '',
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
            ));
        }

        // ปิด stream ด้วย TAG_FINAL เสมอ — รองรับกรณีที่ไฟล์ขนาดเป็น multiple ของ CHUNK_SIZE
        \fwrite($out, \sodium_crypto_secretstream_xchacha20poly1305_push(
            $state,
            '',
            '',
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL,
        ));
    }

    /**
     * @param  resource  $in
     * @param  resource  $out
     */
    private function decryptStreamRaw($in, $out, string $rawKey): void
    {
        $header = \fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($header === false || \strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            throw new RuntimeException('Stream ผิดรูปแบบ หรือไม่มี Header');
        }

        $state = \sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $rawKey);
        $chunkSize = self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

        while (! \feof($in)) {
            $chunk = \fread($in, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $result = \sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($result === false) {
                throw new RuntimeException('ถอดรหัส Chunk ล้มเหลว: ข้อมูลถูกดัดแปลง');
            }
            [$decrypted, $tag] = $result;
            if ($decrypted !== '') {
                \fwrite($out, $decrypted);
            }
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                break;
            }
        }
    }

    /**
     * BLAKE2b incremental hash ของ stream — คืน raw binary digest
     *
     * @param  resource  $stream
     */
    private function hashStreamRaw($stream): string
    {
        $state = \sodium_crypto_generichash_init();
        while (! \feof($stream)) {
            $chunk = \fread($stream, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') {
                break;
            }
            \sodium_crypto_generichash_update($state, $chunk);
        }

        return \sodium_crypto_generichash_final($state);
    }

    /**
     * @return resource
     */
    private function openFile(string $path, string $mode)
    {
        if ($mode === 'rb' && ! \is_readable($path)) {
            throw new RuntimeException("ไม่อาจอ่านไฟล์: {$path}");
        }
        $stream = \fopen($path, $mode);
        if ($stream === false) {
            throw new RuntimeException("เปิดไฟล์ล้มเหลว: {$path}");
        }

        return $stream;
    }
}
