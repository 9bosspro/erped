<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Security;

use Carbon\Carbon;
use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Security\Contracts\RsaHelperInterface;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use RuntimeException;
use Throwable;

/**
 * RsaHelper — RSA Encryption Helper ที่สมบูรณ์ครบวงจร (Security Namespace)
 */
class RsaHelper implements RsaHelperInterface
{
    use DataNormalization, ParsesEncryptionKey;

    private const HYBRID_MAGIC_V1 = "RSAHYB\x01";

    private const HYBRID_MAGIC_V2 = "RSAHYB\x02";

    private const MIN_KEY_BITS = 2048;

    private const AES_KEY_LENGTH = 32;

    private const GCM_IV_LENGTH = 12;

    private const GCM_TAG_LENGTH = 16;

    private const OAEP_SHA256_OVERHEAD = 66;

    private readonly ?string $privateKeyPem;

    private readonly ?string $publicKeyPem;

    private readonly string $passphrase;

    public function __construct(?string $privateKeyPem = null, ?string $publicKeyPem = null)
    {
        $this->privateKeyPem = $privateKeyPem ?? config('core.base::crypto.rsa.private_key');
        $this->publicKeyPem = $publicKeyPem ?? config('core.base::crypto.rsa.public_key');
        $this->passphrase = (string) config('core.base::crypto.rsa.passphrase', '');
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Management
    // ═══════════════════════════════════════════════════════════

    public function withKeys(?string $privateKey = null, ?string $publicKey = null): static
    {
        return new static(
            $privateKey ?? $this->privateKeyPem,
            $publicKey ?? $this->publicKeyPem,
        );
    }

    public function getPrivateKey(): ?string
    {
        return $this->privateKeyPem;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKeyPem;
    }

    public function generateKeyPair(int $bits = 4096): array
    {
        $this->assertKeySize($bits);
        $privateKey = RSA::createKey($bits);

        return [
            'private' => $privateKey->toString('PKCS8'),
            'public' => $privateKey->getPublicKey()->toString('PKCS8'),
        ];
    }

    public function generateProtectedKeyPair(int $bits = 4096, string $passphrase = ''): array
    {
        $this->assertKeySize($bits);
        if ($passphrase === '') {
            throw new RuntimeException('Passphrase is required');
        }

        $privateKey = RSA::createKey($bits);

        return [
            'private' => $privateKey->withPassword($passphrase)->toString('PKCS8'),
            'public' => $privateKey->getPublicKey()->toString('PKCS8'),
        ];
    }

    public function extractPublicKey(string $privateKeyPem): string
    {
        $key = $this->loadRawKey($privateKeyPem);
        if (! $key instanceof PrivateKey) {
            throw new RuntimeException('ต้องเป็น private key เท่านั้น');
        }

        return $key->getPublicKey()->toString('PKCS8');
    }

    public function isKeyPairMatch(string $privateKeyPem, string $publicKeyPem): bool
    {
        try {
            $challenge = random_bytes(32);
            $signature = $this->sign(bin2hex($challenge), $privateKeyPem);

            return $this->verifySignature(bin2hex($challenge), $signature, $publicKeyPem);
        } catch (Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Inspection
    // ═══════════════════════════════════════════════════════════

    public function getKeyType(string $key): string
    {
        return $this->isPrivateKey($key) ? 'private key' : 'public key';
    }

    public function isPrivateKey(string $key): bool
    {
        try {
            return PublicKeyLoader::load($key) instanceof PrivateKey;
        } catch (Throwable) {
            return false;
        }
    }

    public function isPublicKey(string $key): bool
    {
        try {
            $loaded = PublicKeyLoader::load($key);

            return $loaded instanceof PublicKey && ! $loaded instanceof PrivateKey;
        } catch (Throwable) {
            return false;
        }
    }

    public function isValidKey(string $key): bool
    {
        try {
            return PublicKeyLoader::load($key) instanceof PublicKey;
        } catch (Throwable) {
            return false;
        }
    }

    public function getKeyInfo(string $key): array
    {
        $loaded = $this->loadRawKey($key);
        $publicKey = $loaded instanceof PrivateKey ? $loaded->getPublicKey() : $loaded;
        $bits = $publicKey->getLength();

        return [
            'type' => $loaded instanceof PrivateKey ? 'private' : 'public',
            'bits' => $bits,
            'fingerprint' => $this->computeFingerprint($publicKey),
            'max_encrypt_bytes' => (int) floor($bits / 8) - self::OAEP_SHA256_OVERHEAD,
        ];
    }

    public function getKeyFingerprint(string $key): string
    {
        return $this->computeFingerprint($this->loadPublicKey($key));
    }

    public function getKeySize(string $key): int
    {
        return $this->loadPublicKey($key)->getLength();
    }

    public function getMaxEncryptSize(?string $key = null): int
    {
        $keyPem = $key ?? $this->publicKeyPem;
        if (! $keyPem) {
            throw new RuntimeException('RSA key ไม่ได้ตั้งค่า');
        }

        $bits = $this->loadPublicKey($keyPem)->getLength();

        return (int) floor($bits / 8) - self::OAEP_SHA256_OVERHEAD;
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Format Conversion
    // ═══════════════════════════════════════════════════════════

    public function convertToPkcs1(string $key): string
    {
        return $this->loadRawKey($key)->toString('PKCS1');
    }

    public function convertToPkcs8(string $key): string
    {
        return $this->loadRawKey($key)->toString('PKCS8');
    }

    public function loadKeyFromFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("ไม่สามารถอ่านไฟล์ key: {$path}");
        }

        return trim((string) file_get_contents($path));
    }

    // ═══════════════════════════════════════════════════════════
    //  RSA Encrypt / Decrypt
    // ═══════════════════════════════════════════════════════════

    public function encrypt(string $data, ?string $publicKeyPem = null): string
    {
        $key = $this->resolvePublicKey($publicKeyPem);

        return static::encode($key->encrypt($data));
    }

    public function decrypt(string $encryptedBase64, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);
        $result = $key->decrypt(static::decode($encryptedBase64));
        if ($result === false) {
            throw new RuntimeException('RSA decrypt ล้มเหลว');
        }

        return $result;
    }

    public function encryptData(mixed $data, ?string $publicKeyPem = null): string
    {
        $plaintext = $this->serialize($data);
        if (strlen($plaintext) > $this->getMaxEncryptSize($publicKeyPem)) {
            return $this->hybridEncrypt($plaintext, $publicKeyPem);
        }

        return $this->encrypt($plaintext, $publicKeyPem);
    }

    public function decryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed
    {
        $raw = static::decode($encryptedBase64);
        if ($raw !== false && (str_starts_with($raw, self::HYBRID_MAGIC_V1) || str_starts_with($raw, self::HYBRID_MAGIC_V2))) {
            return $this->deserialize($this->hybridDecrypt($encryptedBase64, $privateKeyPem));
        }

        return $this->deserialize($this->decrypt($encryptedBase64, $privateKeyPem));
    }

    // ═══════════════════════════════════════════════════════════
    //  RSA Sign / Verify
    // ═══════════════════════════════════════════════════════════

    public function sign(string $data, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);

        return static::encode($key->sign($data));
    }

    public function verifySignature(string $data, string $signatureBase64, ?string $publicKeyPem = null): bool
    {
        try {
            $key = $this->resolvePublicKey($publicKeyPem);
            $sig = static::decode($signatureBase64);

            return $sig !== false && $key->verify($data, $sig);
        } catch (Throwable) {
            return false;
        }
    }

    public function signData(mixed $data, ?string $privateKeyPem = null): string
    {
        return $this->sign($this->serialize($data), $privateKeyPem);
    }

    public function verifyDataSignature(mixed $data, string $signatureBase64, ?string $publicKeyPem = null): bool
    {
        return $this->verifySignature($this->serialize($data), $signatureBase64, $publicKeyPem);
    }

    public function signPayload(array $payload): array
    {
        $payload['_signed_at'] = Carbon::now()->toIso8601String();

        return [
            'data' => $payload,
            'signature' => $this->sign(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    public function verifyPayload(array $signedPayload, int $maxAgeSeconds = 300): bool
    {
        $data = $signedPayload['data'] ?? null;
        $sig = $signedPayload['signature'] ?? null;
        if (! is_array($data) || ! is_string($sig)) {
            return false;
        }

        $signedAt = $data['_signed_at'] ?? null;
        if (! is_string($signedAt)) {
            return false;
        }

        try {
            $signedTime = Carbon::parse($signedAt);
            if (abs(Carbon::now()->diffInSeconds($signedTime)) > $maxAgeSeconds) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $this->verifySignature(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sig);
    }

    // ═══════════════════════════════════════════════════════════
    //  Hybrid Encryption
    // ═══════════════════════════════════════════════════════════

    public function hybridEncrypt(string $data, ?string $publicKeyPem = null): string
    {
        $key = $this->resolvePublicKey($publicKeyPem);
        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $iv = random_bytes(self::GCM_IV_LENGTH);

        try {
            $encKey = $key->encrypt($aesKey);
            $aad = self::HYBRID_MAGIC_V2.pack('n', strlen($encKey)).$encKey;
            $tag = '';
            $cipher = openssl_encrypt($data, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, $aad, self::GCM_TAG_LENGTH);
            if ($cipher === false) {
                throw new RuntimeException('AES encrypt failed');
            }

            return static::encode($aad.$iv.$tag.$cipher);
        } finally {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($aesKey);
            }
        }
    }

    public function hybridDecrypt(string $encryptedBase64, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);
        $raw = static::decode($encryptedBase64);
        if ($raw === false) {
            throw new RuntimeException('Hybrid base64 decode failed');
        }

        $version = 0;
        if (str_starts_with($raw, self::HYBRID_MAGIC_V2)) {
            $version = 2;
        } elseif (str_starts_with($raw, self::HYBRID_MAGIC_V1)) {
            $version = 1;
        } else {
            throw new RuntimeException('Invalid hybrid format');
        }

        $offset = 7; // MAGIC length
        $encKeyLen = unpack('n', substr($raw, $offset, 2))[1];
        $offset += 2;

        $encKey = substr($raw, $offset, $encKeyLen);
        $offset += $encKeyLen;
        $iv = substr($raw, $offset, self::GCM_IV_LENGTH);
        $offset += self::GCM_IV_LENGTH;
        $tag = substr($raw, $offset, self::GCM_TAG_LENGTH);
        $offset += self::GCM_TAG_LENGTH;
        $cipher = substr($raw, $offset);

        $aesKey = $key->decrypt($encKey);
        $aad = $version === 2 ? substr($raw, 0, 7 + 2 + $encKeyLen) : '';

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($plain === false) {
            throw new RuntimeException('Hybrid decrypt failed');
        }

        return $plain;
    }

    public function hybridEncryptEnvelope(mixed $data, ?string $publicKeyPem = null): array
    {
        $key = $this->resolvePublicKey($publicKeyPem);
        $plaintext = $this->serialize($data);
        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $iv = random_bytes(self::GCM_IV_LENGTH);

        try {
            $encKey = $key->encrypt($aesKey);
            $tag = '';
            $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', self::GCM_TAG_LENGTH);

            return [
                'v' => 2,
                'cipher' => 'rsa-aes-gcm',
                'encrypted_key' => static::encode($encKey),
                'iv' => static::encode($iv),
                'tag' => static::encode($tag),
                'data' => static::encode($cipher),
            ];
        } finally {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($aesKey);
            }
        }
    }

    public function hybridDecryptEnvelope(array $envelope, ?string $privateKeyPem = null): mixed
    {
        $key = $this->resolvePrivateKey($privateKeyPem);
        $aesKey = $key->decrypt(static::decode($envelope['encrypted_key']));
        $plain = openssl_decrypt(
            static::decode($envelope['data']),
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            static::decode($envelope['iv']),
            static::decode($envelope['tag']),
        );

        return $this->deserialize($plain);
    }

    public function hybridEncryptData(mixed $data, ?string $publicKeyPem = null): string
    {
        return $this->hybridEncrypt($this->serialize($data), $publicKeyPem);
    }

    public function hybridDecryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed
    {
        return $this->deserialize($this->hybridDecrypt($encryptedBase64, $privateKeyPem));
    }

    public function encryptKeys(?string $publicKeyPem): array
    {
        $key = $this->resolvePublicKey($publicKeyPem);
        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $encryptedKey = $key->encrypt($aesKey);

        return [
            'aesKey' => $aesKey,
            'encryptedKey' => $encryptedKey,
        ];
    }

    public function aes256gcm(string $data, string $aesKey, string $aad): array
    {
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::GCM_TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES encrypt failed');
        }

        return [
            'iv' => $iv,
            'tag' => $tag,
            'ciphertext' => $ciphertext,
            'aad' => $aad,
        ];
    }

    // ─── Private Logic ──────────────────────────────────────────

    private function loadRawKey(string $pem): PrivateKey|PublicKey
    {
        /** @var PrivateKey|PublicKey $key */
        $key = PublicKeyLoader::load($pem, $this->passphrase);

        return $key;
    }

    private function loadPublicKey(string $pem): PublicKey
    {
        $key = $this->loadRawKey($pem);

        return $key instanceof PrivateKey ? $key->getPublicKey() : $key;
    }

    private function resolvePrivateKey(?string $pem): PrivateKey
    {
        $key = $this->loadRawKey($pem ?? $this->privateKeyPem ?? '');
        if (! $key instanceof PrivateKey) {
            throw new RuntimeException('Private key is required');
        }

        /** @var PrivateKey $key */
        return $key->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS);
    }

    private function resolvePublicKey(?string $pem): PublicKey
    {
        $key = $this->loadPublicKey($pem ?? $this->publicKeyPem ?? '');

        /** @var PublicKey $key */
        return $key->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withPadding(RSA::SIGNATURE_PSS);
    }

    private function computeFingerprint(PublicKey $key): string
    {
        $der = $key->toString('PKCS8');

        return 'SHA256:'.static::encode(hash('sha256', $der, true));
    }

    private function assertKeySize(int $bits): void
    {
        if ($bits < self::MIN_KEY_BITS) {
            throw new RuntimeException('RSA key size ต้องไม่น้อยกว่า '.self::MIN_KEY_BITS);
        }
    }

    private function serialize(mixed $data): string
    {
        return $this->normalizeData($data);
    }

    private function deserialize(string $data): mixed
    {
        $decoded = json_decode($data, true);

        return (json_last_error() === JSON_ERROR_NONE && ! is_numeric($data)) ? $decoded : $data;
    }
}
