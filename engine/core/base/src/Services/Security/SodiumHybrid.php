<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

use RuntimeException;
use SodiumException;

/**
 * SodiumHybrid — X25519 Key Exchange + XChaCha20-Poly1305-IETF (Session Mode)
 * ─────────────────────────────────────────────────────────────────────────────
 * Key exchange  : X25519 (sodium_crypto_kx)
 * Symmetric enc : XChaCha20-Poly1305-IETF (AEAD)
 * Nonce         : 24 bytes random ทุก message
 *
 * flow:
 *   1. ทั้งสองฝ่ายสร้าง keypair แล้วแลก public key กัน
 *   2. เรียก setupSession() → ได้ rx (receive) + tx (transmit) key
 *   3. encrypt() ใช้ tx | decrypt() ใช้ rx
 *      (client.rx == server.tx และกลับกัน)
 */
class SodiumHybrid
{
    // ──────────────────────────────────────────────────────────
    //  CONSTANTS — protected เพื่อให้ child class ใช้ได้
    // ──────────────────────────────────────────────────────────

    protected const NONCE_LEN = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; // 24

    protected const PUBKEY_LEN = SODIUM_CRYPTO_KX_PUBLICKEYBYTES;                      // 32

    // ──────────────────────────────────────────────────────────
    //  STATE — protected เพื่อให้ child class เข้าถึงได้
    // ──────────────────────────────────────────────────────────

    protected string $keypair;

    protected ?string $rx = null;

    protected ?string $tx = null;

    protected bool $sessionReady = false;

    // ──────────────────────────────────────────────────────────
    //  CONSTRUCTOR / FACTORY
    // ──────────────────────────────────────────────────────────

    /**
     * @param  string|null  $keypairBinary  binary keypair (จาก sodium_crypto_kx_keypair)
     *                                      null = สร้างใหม่
     */
    public function __construct(?string $keypairBinary = null)
    {
        try {
            $this->keypair = $keypairBinary ?? \sodium_crypto_kx_keypair();
        } catch (SodiumException $e) {
            throw new RuntimeException('Failed to generate keypair: '.$e->getMessage(), 0, $e);
        }
    }

    /** สร้าง instance ใหม่พร้อม keypair ใหม่เสมอ */
    public static function generate(): static
    {
        return new static;
    }

    /**
     * โหลดจาก hex string (เหมาะสำหรับเก็บใน config / env)
     */
    public static function fromHex(string $hex): static
    {
        return new static(\sodium_hex2bin($hex));
    }

    /**
     * โหลดจาก base64 string (เหมาะสำหรับเก็บใน DB)
     */
    public static function fromBase64(string $base64): static
    {
        $decoded = \base64_decode($base64, strict: true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 keypair string.');
        }

        return new static($decoded);
    }

    // ──────────────────────────────────────────────────────────
    //  PUBLIC KEY EXPORT
    // ──────────────────────────────────────────────────────────

    public function getPublicKeyHex(): string
    {
        return \sodium_bin2hex($this->getPublicKeyBinary());
    }

    public function getPublicKeyBase64(): string
    {
        return \base64_encode($this->getPublicKeyBinary());
    }

    public function getPublicKeyBinary(): string
    {
        try {
            return \sodium_crypto_kx_publickey($this->keypair);
        } catch (SodiumException $e) {
            throw new RuntimeException('Cannot extract public key: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * ดึง Secret Key (X25519) ออกจาก Keypair
     * ⚠️ อันตราย: ใช้เพื่อการ Export หรือสร้าง Box Keypair เท่านั้น
     */
    public function getSecretKeyBinary(): string
    {
        try {
            return \sodium_crypto_kx_secretkey($this->keypair);
        } catch (SodiumException $e) {
            throw new RuntimeException('Cannot extract secret key: '.$e->getMessage(), 0, $e);
        }
    }

    // ──────────────────────────────────────────────────────────
    //  KEYPAIR PERSISTENCE
    // ──────────────────────────────────────────────────────────

    /** Export keypair เป็น hex — ⚠️ รวม private key ต้องเก็บเป็น secret! */
    public function getKeypairHex(): string
    {
        return \sodium_bin2hex($this->keypair);
    }

    /** Export keypair เป็น base64 — ⚠️ รวม private key ต้องเก็บเป็น secret! */
    public function getKeypairBase64(): string
    {
        return \base64_encode($this->keypair);
    }

    // ──────────────────────────────────────────────────────────
    //  SESSION SETUP
    // ──────────────────────────────────────────────────────────

    /**
     * สร้าง session keys จาก public key ของอีกฝ่าย
     *
     * @param  string  $otherPublicKey  hex หรือ base64 ของ public key อีกฝ่าย
     * @param  bool  $isClient  true = client role, false = server role
     *                          client.rx == server.tx (ต้องระบุ role ให้ถูก)
     */
    public function setupSession(string $otherPublicKey, bool $isClient = true): static
    {
        $pubKeyBinary = $this->resolvePublicKey($otherPublicKey);

        try {
            $keys = $isClient
                ? \sodium_crypto_kx_client_session_keys($this->keypair, $pubKeyBinary)
                : \sodium_crypto_kx_server_session_keys($this->keypair, $pubKeyBinary);

            [$this->rx, $this->tx] = $keys;
        } catch (SodiumException $e) {
            throw new RuntimeException('Session setup failed: '.$e->getMessage(), 0, $e);
        } finally {
            \sodium_memzero($pubKeyBinary);
        }

        $this->sessionReady = true;

        return $this;
    }

    public function isSessionReady(): bool
    {
        return $this->sessionReady;
    }

    // ──────────────────────────────────────────────────────────
    //  ENCRYPT / DECRYPT
    // ──────────────────────────────────────────────────────────

    /**
     * เข้ารหัสข้อความ — ใช้ tx key
     *
     * @param  string  $message  plaintext
     * @param  string  $aad  additional authenticated data (ผู้รับต้องส่งเดิมตอน decrypt)
     * @return string base64(nonce + ciphertext + poly1305 tag)
     */
    public function encrypt(string $message, string $aad = ''): string
    {
        $this->assertSession('encrypt');

        /** @var string $tx — guaranteed non-null by assertSession() */
        $tx = $this->tx;

        try {
            $nonce = \random_bytes(self::NONCE_LEN);
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $message,
                $aad,
                $nonce,
                $tx,
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('Encryption failed: '.$e->getMessage(), 0, $e);
        }

        return \base64_encode($nonce.$ciphertext);
    }

    /**
     * ถอดรหัสข้อความ — ใช้ rx key
     *
     * @param  string  $payload  output จาก encrypt()
     * @param  string  $aad  ต้องตรงกับตอน encrypt — ถ้าไม่ตรงจะ throw
     * @return string plaintext
     *
     * @throws RuntimeException ถ้า tag ไม่ผ่าน (tampered / wrong key / wrong aad)
     */
    public function decrypt(string $payload, string $aad = ''): string
    {
        $this->assertSession('decrypt');

        /** @var string $rx — guaranteed non-null by assertSession() */
        $rx = $this->rx;

        $decoded = \base64_decode($payload, strict: true);

        if ($decoded === false || \strlen($decoded) <= self::NONCE_LEN) {
            throw new RuntimeException('Invalid payload format.');
        }

        $nonce = \substr($decoded, 0, self::NONCE_LEN);
        $ciphertext = \substr($decoded, self::NONCE_LEN);

        try {
            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $aad,
                $nonce,
                $rx,
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('Decryption failed: '.$e->getMessage(), 0, $e);
        }

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed: authentication tag mismatch — data may be tampered.',
            );
        }

        return $plaintext;
    }

    /** เข้ารหัสแล้ว encode เป็น hex (บาง transport ชอบ hex) */
    public function encryptHex(string $message, string $aad = ''): string
    {
        $this->assertSession('encrypt');

        /** @var string $tx — guaranteed non-null by assertSession() */
        $tx = $this->tx;

        try {
            $nonce = \random_bytes(self::NONCE_LEN);
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($message, $aad, $nonce, $tx);
        } catch (SodiumException $e) {
            throw new RuntimeException('Encryption failed: '.$e->getMessage(), 0, $e);
        }

        return \sodium_bin2hex($nonce.$ciphertext);
    }

    /** ถอดรหัสจาก hex payload */
    public function decryptHex(string $hexPayload, string $aad = ''): string
    {
        $this->assertSession('decrypt');

        /** @var string $rx — guaranteed non-null by assertSession() */
        $rx = $this->rx;

        if ($hexPayload === '' || ! \ctype_xdigit($hexPayload)) {
            throw new RuntimeException('Invalid hex payload: expected a non-empty hex string.');
        }

        $bin = \sodium_hex2bin($hexPayload);

        if (\strlen($bin) <= self::NONCE_LEN) {
            throw new RuntimeException('Invalid payload: too short to contain nonce + ciphertext.');
        }

        $nonce = \substr($bin, 0, self::NONCE_LEN);
        $ciphertext = \substr($bin, self::NONCE_LEN);

        try {
            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $rx);
        } catch (SodiumException $e) {
            throw new RuntimeException('Decryption failed: '.$e->getMessage(), 0, $e);
        }

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed: authentication tag mismatch — data may be tampered.',
            );
        }

        return $plaintext;
    }

    // ──────────────────────────────────────────────────────────
    //  CLEANUP
    // ──────────────────────────────────────────────────────────

    /** ลบ session keys ออกจาก memory อย่างปลอดภัย */
    public function wipe(): void
    {
        if (isset($this->rx)) {
            \sodium_memzero($this->rx);
            $this->rx = null;
        }
        if (isset($this->tx)) {
            \sodium_memzero($this->tx);
            $this->tx = null;
        }

        $this->sessionReady = false;
    }

    /**
     * รีเซ็ต session keys แล้ว setup ใหม่กับ peer อื่น — keypair คงเดิม
     * ใช้เมื่อต้องการเปลี่ยน session โดยไม่สร้าง keypair ใหม่
     */
    public function resetSession(): static
    {
        $this->wipe();

        return $this;
    }

    public function __destruct()
    {
        $this->wipe();
    }

    // ──────────────────────────────────────────────────────────
    //  PROTECTED HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * ตรวจว่า session พร้อมก่อน encrypt/decrypt
     *
     * @param  string  $op  ชื่อ operation เพื่อ error message ที่ชัดเจน
     */
    protected function assertSession(string $op = 'this operation'): void
    {
        if (! $this->sessionReady || $this->rx === null || $this->tx === null) {
            throw new RuntimeException(
                "Cannot {$op}: session not established. Call setupSession() first.",
            );
        }
    }

    /**
     * แปลง public key string (binary, hex หรือ base64) → binary พร้อมตรวจขนาด
     *
     * @param  string  $key  Public Key ในรูปแบบ binary(32), hex(64) หรือ base64(44)
     * @return string binary(32)
     *
     * @throws RuntimeException ถ้า Format หรือขนาดไม่ถูกต้อง
     */
    protected function resolvePublicKey(string $key): string
    {
        $decoded = \Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey::decodeKey($key);

        if ($decoded === null || \strlen($decoded) !== self::PUBKEY_LEN) {
            throw new RuntimeException(
                'Invalid public key format or length. Expected binary(32), hex(64), or base64(44).',
            );
        }

        return $decoded;
    }
}
