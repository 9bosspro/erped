<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use InvalidArgumentException;
use RuntimeException;
use SodiumException;

/**
 * HandlesKeyManagement — static key generation, exchange, and utility methods
 *
 * ครอบคลุม: Key generation, Key exchange (X25519), Ed25519 combined sign/verify,
 * AEAD nonce sizes, utility helpers (equals, memzero, compare, assertSodium)
 */
trait HandlesKeyManagement
{
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

    /**
     * Constant-time comparison — คืน 0 (เท่ากัน), -1 (a < b), 1 (a > b)
     *
     * @throws RuntimeException เมื่อ strings มีความยาวต่างกัน
     */
    public static function compare(string $a, string $b): int
    {
        try {
            return \sodium_compare($a, $b);
        } catch (SodiumException $e) {
            throw new RuntimeException('sodium_compare ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function equals(string $a, string $b): bool
    {
        return \hash_equals($a, $b);
    }

    public static function isAes256GcmAvailable(): bool
    {
        return \sodium_crypto_aead_aes256gcm_is_available();
    }

    /**
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
     * Generate a secure random key (32 bytes) สำหรับ Symmetric Encryption / AEAD
     *
     * @return string base64 encoded key (No Padding) หรือ raw binary
     */
    public function generateEncryptionKey(bool $useHex = true): string
    {
        return self::generateKeyMaster($useHex);
    }

    /**
     * Generate a BLAKE2b KDF Master Key (32 bytes) — ใช้กับ kdfDerive()
     *
     * แยกจาก generateEncryptionKey() เพื่อ domain separation
     *
     * @return string base64 encoded key (No Padding) หรือ raw binary
     */
    public function generateKdfKey(bool $useHex = true): string
    {
        $key = \sodium_crypto_kdf_keygen();

        return self::encodeKey($key, $useHex);
    }

    /**
     * Generate a SipHash-2-4 key (16 bytes)
     *
     * @return string base64 encoded key (No Padding) or raw binary
     */
    public function generateShortHashKey(bool $useBinary = false): string
    {
        $key = \sodium_crypto_shorthash_keygen();

        return self::encodeKey($key, $useBinary);
    }

    public function shortHash(string $message, string $key, bool $useBinary = false): string
    {
        try {
            $hash = \sodium_crypto_shorthash($message, $key);

            return self::encodeKey($hash, $useBinary);
        } catch (SodiumException $e) {
            throw new RuntimeException('SipHash-2-4 hash failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate Ed25519 Key Pair for Sign
     *
     * @return array{public: string, secret: string, keypair: string}
     */
    public function generateSignatureKeyPair(?string $signSeed, bool $useBinary = false): array
    {
        // $kp = \sodium_crypto_sign_keypair();
        $signSeed = $signSeed ?? $this->appKey;
        $signSeed = $this->resolveKey($signSeed, 32);
        $kp = \sodium_crypto_sign_seed_keypair($signSeed);
        $result = [
            'public' => self::encodeKey(\sodium_crypto_sign_publickey($kp), $useBinary),
            'secret' => self::encodeKey(\sodium_crypto_sign_secretkey($kp), $useBinary),
            'keypair' => self::encodeKey($kp, $useBinary),
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * Generate X25519 Box Key Pair
     *
     * @return array{public: string, secret: string, keypair: string}
     */
    public function generateBoxKeyPair(?string $exchangeSeed, bool $useBinary = false): array
    {
        // $kp = \sodium_crypto_box_keypair();
        $exchangeSeed = $exchangeSeed ?? $this->appKey;
        $exchangeSeed = $this->resolveKey($exchangeSeed, 32);
        $kp = \sodium_crypto_box_seed_keypair($exchangeSeed);
        $result = [
            'public' => self::encodeKey(\sodium_crypto_box_publickey($kp), $useBinary),
            'secret' => self::encodeKey(\sodium_crypto_box_secretkey($kp), $useBinary),
            'keypair' => self::encodeKey($kp, $useBinary),
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * Generate X25519 KX Key Pair
     *
     * @return array{public: string, secret: string, keypair: string}
     */
    public function generateKxKeyPair(?string $cryptoSeed, bool $useBinary = false): array
    {
        //  $kp = \sodium_crypto_kx_keypair();
        $cryptoSeed = $cryptoSeed ?? $this->appKey;
        $cryptoSeed = $this->resolveKey($cryptoSeed, 32);
        $kp = \sodium_crypto_kx_seed_keypair($cryptoSeed);
        $result = [
            'public' => self::encodeKey(\sodium_crypto_kx_publickey($kp), $useBinary),
            'secret' => self::encodeKey(\sodium_crypto_kx_secretkey($kp), $useBinary),
            'keypair' => self::encodeKey($kp, $useBinary),
        ];
        \sodium_memzero($kp);

        return $result;
    }

    /**
     * Client session keys หลัง X25519 DH
     *
     * @return array{rx: string, tx: string}
     */
    public function kxClientKeys(string $clientKeyPairb64, string $serverPublicKeyb64, bool $useBinary = false): array
    {
        $clientKeyPair = self::decodeKey($clientKeyPairb64);
        //  dd($clientKeyPair);
        $serverPublicKey = self::decodeKey($serverPublicKeyb64);
        [$rx, $tx] = \sodium_crypto_kx_client_session_keys($clientKeyPair, $serverPublicKey);
        \sodium_memzero($clientKeyPair);

        $rx = self::encodeKey($rx, $useBinary); // encodeKey
        $tx = self::encodeKey($tx, $useBinary);

        $result = ['rx' => $rx, 'tx' => $tx];
        \sodium_memzero($rx);
        \sodium_memzero($tx);

        return $result;
    }

    /**
     * Server session keys หลัง X25519 DH
     *
     * @return array{rx: string, tx: string}
     */
    public function kxServerKeys(string $serverKeyPairb64, string $clientPublicKeyb64, bool $useBinary = false): array
    {
        $serverKeyPair = self::decodeKey($serverKeyPairb64);
        $clientPublicKey = self::decodeKey($clientPublicKeyb64);
        [$rx, $tx] = \sodium_crypto_kx_server_session_keys($serverKeyPair, $clientPublicKey);
        \sodium_memzero($serverKeyPair);
        $rx = self::encodeKey($rx, $useBinary);
        $tx = self::encodeKey($tx, $useBinary);

        $result = ['rx' => $rx, 'tx' => $tx];
        \sodium_memzero($rx);
        \sodium_memzero($tx);

        return $result;
    }

    /**
     * Raw X25519 ECDH shared secret (32 bytes) — ต้องผ่าน KDF ก่อนนำไปใช้
     *
     * @throws RuntimeException เมื่อ ECDH ล้มเหลว
     */
    public function ecdhSharedSecret(string $ourSkb64, string $theirPkb64, bool $useBinary = false): string
    {
        try {
            $ourSk = $this->resolveKey($ourSkb64);
            $theirPk = $this->resolveKey($theirPkb64);

            return \sodium_crypto_scalarmult($ourSk, $theirPk);
        } catch (SodiumException $e) {
            throw new RuntimeException('X25519 ECDH ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * เซ็นพร้อมรวม signature ไว้ใน message (combined format — 64 + N bytes)
     *
     * @throws RuntimeException เมื่อ sign ล้มเหลว
     */
    public function signCombined(string $message, string $signingSecretKeyb64, bool $useBinary = false): string
    {
        try {
            $signingSecretKey = $this->resolveKey($signingSecretKeyb64);

            return \sodium_crypto_sign($message, $signingSecretKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 signCombined ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * ตรวจสอบและแยก message ออกจาก signed combined message
     *
     * @throws RuntimeException เมื่อ signature ไม่ถูกต้อง
     */
    public function openSigned(string $signed, string $signingPublicKeyb64, bool $useBinary = false): string
    {
        try {
            $signingPublicKey = $this->resolveKey($signingPublicKeyb64);
            $result = \sodium_crypto_sign_open($signed, $signingPublicKey);
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 openSigned ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }

        if ($result === false) {
            throw new RuntimeException('Ed25519 signature ไม่ถูกต้อง — message อาจถูกดัดแปลง');
        }

        return $result;
    }
}
