<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

/**
 * HandlesHashingKdf — BLAKE2b Generic Hash + KDF Subkey Derivation
 *
 * ครอบคลุม:
 *  - hash      (BLAKE2b generic hash — คืน hex)
 *  - kdfDerive (BLAKE2b KDF — derive subkey จาก master key)
 */
trait HandlesHashingKdf
{
    /**
     * BLAKE2b Generic Hash — Returns hex
     */
    public function hash(string $message, string $keyBase64 = '', int $length = SODIUM_CRYPTO_GENERICHASH_BYTES): string
    {
        $key = ($keyBase64 !== '') ? $this->resolveKey($keyBase64) : '';

        return \sodium_bin2hex(\sodium_crypto_generichash($message, $key, $length));
    }

    /**
     * BLAKE2b KDF — derive sub-key from master key — Returns Base64
     */
    public function kdfDerive(string $masterKeyBase64, int $subkeyId, string $context = self::KDF_DEFAULT_CONTEXT, int $length = 32, bool $useBinary = false): string
    {
        $key = $this->resolveKey($masterKeyBase64, 32);
        $ctx = \str_pad(\substr($context, 0, 8), 8, "\0");

        $derived = \sodium_crypto_kdf_derive_from_key($length, $subkeyId, $ctx, $key);
        \sodium_memzero($key);

        return self::encodeKey($derived, $useBinary);
    }
}
