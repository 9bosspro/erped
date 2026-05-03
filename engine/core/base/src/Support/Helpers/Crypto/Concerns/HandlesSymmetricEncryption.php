<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use InvalidArgumentException;
use RuntimeException;
use SodiumException;

/**
 * HandlesSymmetricEncryption — XSalsa20-Poly1305 SecretBox + XChaCha20-Poly1305 AEAD
 *
 * ครอบคลุม:
 *  - encrypt / decrypt / rotateKey  (SecretBox ผ่าน AEAD)
 *  - encryptAead / decryptAead      (XChaCha20-Poly1305 IETF, versioned format)
 *  - aeadEncrypt / aeadDecrypt      (multi-algorithm AEAD พร้อม AAD)
 */
trait HandlesSymmetricEncryption
{
    /**
     * ตรวจสอบว่า CPU รองรับ AES-256-GCM — throw ถ้าไม่รองรับ
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
     * Symmetric Encryption (SecretBox) — Returns Base64 (No Padding by default)
     *
     * @param  array<mixed>|bool|float|int|object|string  $message
     */
    public function encrypt(mixed $message, ?string $keyBase64 = null, bool $useBinary = false): string
    {
        return $this->encryptAead('encrypt:', $keyBase64, $message, null, $useBinary);
    }

    /**
     * Symmetric Decryption from Base64
     */
    public function decrypt(string $payload, ?string $keyBase64 = null, bool $useBinary = false): mixed
    {
        return $this->decryptAead('encrypt:', $keyBase64, $payload, null, $useBinary);
    }

    /**
     * Key Rotation — Decrypt with old key and re-encrypt with current key
     */
    public function rotateKey(string $payload, string $oldKey): string
    {
        $plaintext = $this->decrypt($payload, $oldKey);
        $newCiphertext = $this->encrypt($plaintext);

        if (\is_string($plaintext)) {
            self::memzero($plaintext);
        }

        return $newCiphertext;
    }

    /**
     * AEAD Encrypt (XChaCha20-Poly1305-IETF) — Returns Base64
     *
     * @param  array<mixed>|bool|float|int|object|string  $message
     */
    public function encryptAead(string $aad, ?string $keyBase64, mixed $message, ?string $nonce = null, bool $useBinary = false): string
    {
        $message = $this->normalizeData($message);
        $key = $this->resolveKey($keyBase64, 32);

        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $autoNonce = ($nonce === null);
        $resolvedNonce = $nonce ?? \random_bytes($nonceLen);

        if (\strlen($resolvedNonce) !== $nonceLen) {
            throw new RuntimeException("AEAD encrypt failed: Nonce must be {$nonceLen} bytes");
        }

        try {
            $ciphertext = \sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $message,
                $aad,
                $resolvedNonce,
                $key,
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('AEAD encrypt failed: '.$e->getMessage(), 0, $e);
        }

        $data = $autoNonce
            ? self::FORMAT_AEAD_V1.$resolvedNonce.$ciphertext
            : self::FORMAT_AEAD_V1.$ciphertext;

        return self::encodeKey($data, $useBinary);
    }

    /**
     * AEAD Decrypt from Base64
     */
    public function decryptAead(string $aad, ?string $keyBase64, string $payload, ?string $nonce = null, bool $useBinary = false): mixed
    {
        $key = $this->resolveKey($keyBase64, 32);

        if (! $useBinary) {
            $payload = $this->decodeKey($payload);
            if ($payload === null) {
                throw new RuntimeException('AEAD decrypt failed: Invalid encoded input (Hex/Base64)');
            }
        }

        if (empty($payload)) {
            throw new RuntimeException('AEAD decrypt failed: Empty payload');
        }

        $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $extractedNonce = '';
        $ciphertext = '';

        if ($payload[0] === self::FORMAT_AEAD_V1) {
            if ($nonce !== null) {
                $extractedNonce = $nonce;
                $ciphertext = \substr($payload, 1);
            } else {
                if (\strlen($payload) < 1 + $nonceLen) {
                    throw new RuntimeException('AEAD v1 payload too short');
                }
                $extractedNonce = \substr($payload, 1, $nonceLen);
                $ciphertext = \substr($payload, 1 + $nonceLen);
            }
        } else {
            if (\ord($payload[0]) >= 0x03) {
                throw new RuntimeException('AEAD: Unsupported format version 0x'.\bin2hex($payload[0]));
            }

            if (\strlen($payload) < $nonceLen) {
                throw new RuntimeException('AEAD legacy payload too short');
            }
            $extractedNonce = \substr($payload, 0, $nonceLen);
            $ciphertext = \substr($payload, $nonceLen);
        }

        if (\strlen($extractedNonce) !== $nonceLen) {
            throw new RuntimeException('AEAD decrypt failed: Invalid nonce length');
        }

        try {
            $plaintext = \sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $aad,
                $extractedNonce,
                $key,
            );
        } catch (SodiumException $e) {
            throw new RuntimeException('AEAD decrypt failed: '.$e->getMessage());
        }

        if ($plaintext === false) {
            throw new RuntimeException('AEAD decrypt failed: Authentication failed (Invalid Key or AAD)');
        }

        return $this->deserializeData($plaintext);
    }

    /**
     * AEAD Encrypt — เข้ารหัสพร้อม Additional Authenticated Data (AAD)
     * รองรับหลาย algorithm (XCHACHA20POLY1305_IETF, CHACHA20POLY1305_IETF, AES256GCM)
     *
     * @throws InvalidArgumentException เมื่อ algorithm ไม่รองรับ
     * @throws RuntimeException เมื่อ encrypt ล้มเหลวหรือ CPU ไม่รองรับ AES
     */
    public function aeadEncrypt(string $message, string $additionalData, string $nonce, string $keyb64, string $algo = self::AEAD_XCHACHA20POLY1305_IETF): string
    {
        $rawKey = $this->resolveKey($keyb64, 32);
        if ($rawKey === null) {
            throw new InvalidArgumentException('aeadEncrypt: key base64 decode ล้มเหลว — ตรวจสอบ key format');
        }

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
     * @throws InvalidArgumentException เมื่อ algorithm ไม่รองรับ
     * @throws RuntimeException เมื่อ authentication ล้มเหลวหรือถอดรหัสไม่ได้
     */
    public function aeadDecrypt(string $ciphertext, string $additionalData, string $nonce, string $key, string $algo = self::AEAD_XCHACHA20POLY1305_IETF): string
    {
        $rawKey = $this->resolveKey($key, 32);
        if ($rawKey === null) {
            throw new InvalidArgumentException('aeadDecrypt: key base64 decode ล้มเหลว — ตรวจสอบ key format');
        }

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
}
