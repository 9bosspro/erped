<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use InvalidArgumentException;
use RuntimeException;
use SodiumException;

/**
 * HandlesSignatures — Ed25519 Detached Signatures + Multi-part + Stream Signatures
 *
 * ครอบคลุม:
 *  - sign / verify                              (Detached signature)
 *  - signInit / signUpdate / signFinalCreate / signFinalVerify (Multi-part)
 *  - signStream / verifyStreamSignature         (BLAKE2b digest + Ed25519)
 */
trait HandlesSignatures
{
    /**
     * sign Detached Signature — Returns Base64
     */
    public function sign(mixed $message, string $secretKeyBase64, bool $useBinary = false): string
    {
        $message = $this->normalizeData($message);
        $sk = $this->resolveKey($secretKeyBase64, 64);
        $sig = \sodium_crypto_sign_detached($message, $sk);
        \sodium_memzero($sk);

        return self::encodeKey($sig, $useBinary);
    }

    /**
     * ตรวจสอบลายเซ็น Detached จาก Base64
     */
    public function verify(string $signature, mixed $message, string $publicKeyBase64, bool $useBinary = false): bool
    {
        $decoded = $this->decodeKey($signature);
        if ($decoded === null) {
            throw new RuntimeException('verify: signature decode ล้มเหลว — ตรวจสอบ signature format');
        }
        $signature = $decoded;
        /*   if (! $useBinary) {
            $decoded = $this->decodeKey($signature);
            if ($decoded === null) {
                throw new RuntimeException('verify: signature decode ล้มเหลว — ตรวจสอบ signature format');
            }
            $signature = $decoded;
        } */
        $message = $this->normalizeData($message);

        $pk = $this->resolveKey($publicKeyBase64, 32);
        if ($pk === null) {
            return false;
        }
        $message = $this->normalizeData($message);

        return \sodium_crypto_sign_verify_detached(
            $signature,
            $message,
            $pk,
        );
    }

    public function signInit(): string
    {
        return \sodium_crypto_sign_init();
    }

    public function signUpdate(string &$state, string $chunk): void
    {
        \sodium_crypto_sign_update($state, $chunk);
    }

    /**
     * Finalize Multi-part Signature — Returns Base64
     */
    public function signFinalCreate(string $state, string $secretKeyBase64, bool $useBinary = false): string
    {
        $sk = $this->resolveKey($secretKeyBase64);
        $sig = \sodium_crypto_sign_final_create($state, $sk);
        \sodium_memzero($sk);

        return self::encodeKey($sig, $useBinary);
    }

    /**
     * ตรวจสอบลายเซ็น Multi-part จาก Base64
     */
    public function signFinalVerify(string $state, string $signatureBase64, string $publicKeyBase64): bool
    {
        $sig = $this->resolveKey($signatureBase64);
        $pk = $this->resolveKey($publicKeyBase64);

        if ($sig === null || $pk === null) {
            return false;
        }

        return \sodium_crypto_sign_final_verify(
            $state,
            $sig,
            $pk,
        );
    }

    /**
     * สร้างลายเซ็นสำหรับ Stream — คืน Base64
     * กลไก: BLAKE2b incremental hash ทั้ง stream → Ed25519 sign ผลลัพธ์
     *
     * @param  resource  $inputStream
     *
     * @throws InvalidArgumentException เมื่อ secret key decode ล้มเหลว
     * @throws RuntimeException เมื่อ sign ล้มเหลว
     */
    public function signStream($inputStream, string $secretKeyBase64, bool $useBinary = false): string
    {
        $sk = $this->resolveKey($secretKeyBase64, 32);
        if ($sk === null) {
            throw new InvalidArgumentException('signStream: secret key base64 decode ล้มเหลว — ตรวจสอบ key format');
        }

        try {
            $sig = \sodium_crypto_sign_detached($this->hashStreamRaw($inputStream), $sk);
        } catch (SodiumException $e) {
            throw new RuntimeException('signStream: Ed25519 sign ล้มเหลว: ' . $e->getMessage(), 0, $e);
        } finally {
            \sodium_memzero($sk);
        }

        return self::encodeKey($sig, $useBinary);
    }

    /**
     * ตรวจสอบลายเซ็น Stream
     *
     * @param  resource  $inputStream
     *
     * @throws InvalidArgumentException เมื่อ signature หรือ public key decode ล้มเหลว
     */
    public function verifyStreamSignature($inputStream, string $signatureBase64, string $publicKeyBase64): bool
    {
        $sig = $this->resolveKey($signatureBase64);
        if ($sig === null) {
            throw new InvalidArgumentException('verifyStreamSignature: signature base64 decode ล้มเหลว');
        }

        $pk = $this->resolveKey($publicKeyBase64);
        if ($pk === null) {
            throw new InvalidArgumentException('verifyStreamSignature: public key base64 decode ล้มเหลว');
        }

        return \sodium_crypto_sign_verify_detached(
            $sig,
            $this->hashStreamRaw($inputStream),
            $pk,
        );
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
}
