<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use RuntimeException;

/**
 * HandlesAsymmetricEncryption — Box (Authenticated) & Seal (Anonymous) X25519
 *
 * ครอบคลุม:
 *  - box / boxUrlSafe / boxOpen / boxOpenUrlSafe   (Authenticated DH Box)
 *  - seal / sealUrlSafe / sealOpen / sealOpenUrlSafe (Anonymous Sealed Box)
 *  - sealOpenWithKeyPair                            (Sealed Box ด้วย Keypair ตรงๆ)
 */
trait HandlesAsymmetricEncryption
{
    /**
     * Box Encrypt (Authenticated) — คืน Base64 No-Padding (alphabet: A-Za-z0-9+/)
     */
    public function box(string $message, string $recipientPublicKey, string $senderSecretKey): string
    {
        return self::encodeb64($this->boxEncryptBinary($message, $recipientPublicKey, $senderSecretKey));
    }

    /**
     * Box Encrypt (Authenticated) — คืน Base64url No-Padding (alphabet: A-Za-z0-9_-)
     */
    public function boxUrlSafe(string $message, string $recipientPublicKey, string $senderSecretKey): string
    {
        return self::encodeUrlSafe($this->boxEncryptBinary($message, $recipientPublicKey, $senderSecretKey));
    }

    /**
     * Box Decrypt จาก Base64 No-Padding
     *
     * @throws RuntimeException เมื่อ payload decode ล้มเหลว หรือ authentication ไม่ผ่าน
     */
    public function boxOpen(string $payloadBase64, string $senderPublicKey, string $recipientSecretKey): string
    {
        $payload = self::decodeb64($payloadBase64);
        if ($payload === false || \strlen($payload) <= SODIUM_CRYPTO_BOX_NONCEBYTES) {
            throw new RuntimeException('boxOpen: payload decoding failed');
        }

        return $this->boxOpenRaw($payload, $senderPublicKey, $recipientSecretKey);
    }

    /**
     * Box Decrypt จาก Base64url No-Padding
     */
    public function boxOpenUrlSafe(string $payloadUrlSafe, string $senderPublicKey, string $recipientSecretKey): string
    {
        $payload = self::decodeUrlSafe($payloadUrlSafe);
        if ($payload === false || \strlen($payload) <= SODIUM_CRYPTO_BOX_NONCEBYTES) {
            throw new RuntimeException('boxOpenUrlSafe: payload decoding failed');
        }

        return $this->boxOpenRaw($payload, $senderPublicKey, $recipientSecretKey);
    }

    /**
     * Sealed Box Encrypt (Anonymous) — คืน Base64
     */
    public function seal(string $message, string $recipientPublicKey, bool $useBinary = true): string
    {
        $pk = $this->resolveKey($recipientPublicKey);
        if ($pk === null) {
            throw new RuntimeException('seal: public key decoding failed');
        }

        return self::encodeKey(\sodium_crypto_box_seal($message, $pk), $useBinary);
    }

    /**
     * Sealed Box Encrypt (Anonymous) — คืน Base64url
     */
    public function sealUrlSafe(string $message, string $recipientPublicKey, bool $useBinary = false): string
    {
        $pk = $this->resolveKey($recipientPublicKey);
        if ($pk === null) {
            throw new RuntimeException('sealUrlSafe: public key decoding failed');
        }

        return self::encodeKey(\sodium_crypto_box_seal($message, $pk), $useBinary);
    }

    /**
     * Sealed Box Decrypt จาก Base64 No-Padding
     */
    public function sealOpen(string $payloadBase64, string $recipientPublicKey, string $recipientSecretKey): string
    {
        $payload = self::decodeb64($payloadBase64);
        if ($payload === false) {
            throw new RuntimeException('sealOpen: payload decoding failed');
        }

        return $this->sealOpenRaw($payload, $recipientPublicKey, $recipientSecretKey);
    }

    /**
     * Sealed Box Decrypt จาก Base64url No-Padding
     */
    public function sealOpenUrlSafe(string $payloadUrlSafe, string $recipientPublicKey, string $recipientSecretKey): string
    {
        $payload = self::decodeUrlSafe($payloadUrlSafe);
        if ($payload === false) {
            throw new RuntimeException('sealOpenUrlSafe: payload decoding failed');
        }

        return $this->sealOpenRaw($payload, $recipientPublicKey, $recipientSecretKey);
    }

    /**
     * Sealed Box Decrypt — โดยใช้ Keypair (Base64) ตรงๆ
     */
    public function sealOpenWithKeyPair(string $payloadBase64, string $keyPairBase64): string
    {
        $binary = $this->resolveKey($payloadBase64);
        $kp = $this->resolveKey($keyPairBase64);

        if ($binary === null || $kp === null) {
            throw new RuntimeException('sealOpenWithKeyPair: decoding failed');
        }

        $plaintext = \sodium_crypto_box_seal_open($binary, $kp);
        \sodium_memzero($kp);

        if ($plaintext === false) {
            throw new RuntimeException('SealedBox ถอดรหัสล้มเหลว: กุญแจไม่ถูกต้องหรือข้อมูลถูกดัดแปลง');
        }

        return $plaintext;
    }

    private function boxEncryptBinary(string $message, string $recipientPublicKey, string $senderSecretKey): string
    {
        $pk = $this->resolveKey($recipientPublicKey, 32);
        $sk = $this->resolveKey($senderSecretKey, 32);

        if ($pk === null || $sk === null) {
            throw new RuntimeException('boxEncryptBinary: key decoding failed');
        }

        $nonce = \random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $data = $nonce.\sodium_crypto_box($message, $nonce, $kp);
        \sodium_memzero($kp);

        return $data;
    }

    private function boxOpenRaw(string $binary, string $senderPublicKeyb64, string $recipientSecretKeyb64): string
    {
        $pk = $this->resolveKey($senderPublicKeyb64);
        $sk = $this->resolveKey($recipientSecretKeyb64);

        if ($pk === null || $sk === null) {
            throw new RuntimeException('boxOpenRaw: key decoding failed');
        }

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

        return (string) $plaintext;
    }

    private function sealOpenRaw(string $binary, string $recipientPublicKeyb64, string $recipientSecretKeyb64): string
    {
        $pk = $this->resolveKey($recipientPublicKeyb64);
        $sk = $this->resolveKey($recipientSecretKeyb64);

        if ($pk === null || $sk === null) {
            throw new RuntimeException('sealOpenRaw: key decoding failed');
        }

        $kp = \sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        \sodium_memzero($sk);

        $plaintext = \sodium_crypto_box_seal_open($binary, $kp);
        \sodium_memzero($kp);

        if ($plaintext === false) {
            throw new RuntimeException('SealedBox ถอดรหัสล้มเหลว: กุญแจไม่ถูกต้อง');
        }

        return (string) $plaintext;
    }
}
