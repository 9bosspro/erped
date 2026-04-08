<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Security;

use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Security\Contracts\EncryptionHelperInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * EncryptionHelper — Symmetric Encryption Helper ที่สมบูรณ์ครบวงจร
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
 *
 *  sealEncrypt($data, $pubKey)  — encrypt ด้วย public key (ใครก็ encrypt ได้)
 *  sealDecrypt($enc, $keyPair)  — decrypt ด้วย key pair (เฉพาะเจ้าของ)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Password-Based  (PBKDF2 + AES-256-GCM)
 * ═══════════════════════════════════════════════════════════════
 *
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
 */
final class EncryptionHelper implements EncryptionHelperInterface
{
    use DataNormalization, ParsesEncryptionKey;

    // ─── Constants ──────────────────────────────────────────────

    private const CIPHER_GCM = 'aes-256-gcm';

    private const CIPHER_CBC = 'aes-256-cbc';

    private const GCM_IV_LENGTH = 12;

    private const GCM_TAG_LENGTH = 16;

    private const CBC_IV_LENGTH = 16;

    private const AES_KEY_LENGTH = 32;

    private const STREAM_CHUNK_SIZE = 64 * 1024;

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

    public function encrypt(mixed $data, ?string $key = null): string
    {
        return $this->doEncryptGcm($this->serialize($data), $this->resolveKey($key));
    }

    public function decrypt(string $encrypted, ?string $key = null): mixed
    {
        return $this->deserialize(
            $this->doDecryptGcm($encrypted, $this->resolveKey($key)),
        );
    }

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
            throw new RuntimeException('AES-256-GCM+AAD encrypt ล้มเหลว: '.openssl_error_string());
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'gcm-aad',
            'iv' => $this->encodeb64($iv),
            'tag' => $this->encodeb64($tag),
            'aad_hash' => $this->encodeb64(hash('sha256', $aad, true)),
            'data' => $this->encodeb64($ciphertext),
        ]);
    }

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
    //  AES-256-CBC + HMAC
    // ═══════════════════════════════════════════════════════════

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
            throw new RuntimeException('AES-256-CBC encrypt ล้มเหลว: '.openssl_error_string());
        }

        $mac = hash_hmac('sha256', $iv.$ciphertext, $resolvedKey, true);

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'cbc',
            'iv' => self::encode($iv),
            'mac' => self::encode($mac),
            'data' => self::encode($ciphertext),
        ]);
    }

    public function decryptCbc(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['iv', 'mac', 'data']);

        $resolvedKey = $this->resolveKey($key);
        $iv = $this->safeBase64Decode($envelope['iv'], 'iv');
        $mac = $this->safeBase64Decode($envelope['mac'], 'mac');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $expectedMac = hash_hmac('sha256', $iv.$ciphertext, $resolvedKey, true);

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
    //  XChaCha20-Poly1305
    // ═══════════════════════════════════════════════════════════

    public function encryptSodium(mixed $data, ?string $key = null): string
    {
        $plaintext = $this->serialize($data);
        $sodiumKey = $this->deriveSodiumKey($key);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',
            $nonce,
            $sodiumKey,
        );

        sodium_memzero($sodiumKey);

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'xchacha20',
            'nonce' => self::encode($nonce),
            'data' => self::encode($ciphertext),
        ]);
    }

    public function decryptSodium(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $this->assertEnvelopeFields($envelope, ['nonce', 'data']);

        $sodiumKey = $this->deriveSodiumKey($key);
        $nonce = $this->safeBase64Decode($envelope['nonce'], 'nonce');
        $ciphertext = $this->safeBase64Decode($envelope['data'], 'data');

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
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
    //  Deterministic (searchable fields)
    // ═══════════════════════════════════════════════════════════

    public function encryptDeterministic(mixed $data, ?string $key = null): string
    {
        $plaintext = $this->serialize($data);
        $resolvedKey = $this->resolveKey($key);

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

        sodium_memzero($plaintext);

        if ($ciphertext === false) {
            throw new RuntimeException('Deterministic encrypt ล้มเหลว');
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'det-gcm',
            'iv' => self::encode($syntheticIv),
            'tag' => self::encode($tag),
            'data' => self::encode($ciphertext),
        ]);
    }

    public function decryptDeterministic(string $encrypted, ?string $key = null): mixed
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
        );

        if ($plaintext === false) {
            throw new RuntimeException('Deterministic decrypt ล้มเหลว');
        }

        try {
            return $this->deserialize($plaintext);
        } finally {
            self::memzero($plaintext);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Expiring / URL-Safe / Stream
    // ═══════════════════════════════════════════════════════════

    public function encryptExpiring(mixed $data, int $ttlSeconds = 300, ?string $key = null): string
    {
        $wrapper = [
            '_payload' => $data,
            '_exp' => time() + max(0, $ttlSeconds),
        ];

        return $this->encrypt($wrapper, $key);
    }

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

    public function encryptUrlSafe(mixed $data, ?string $key = null): string
    {
        return self::encodeUrlSafe($this->encrypt($data, $key));
    }

    public function decryptUrlSafe(string $encrypted, ?string $key = null): mixed
    {
        return $this->decrypt(self::decodeUrlSafe($encrypted), $key);
    }

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

                $tag = feof($inputStream)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);

                if (fwrite($outputStream, $encrypted) === false) {
                    throw new RuntimeException('เขียน output stream ล้มเหลว');
                }
            }

            return self::encode($header);
        } finally {
            sodium_memzero($sodiumKey);
        }
    }

    public function decryptStream($inputStream, $outputStream, string $headerBase64, ?string $key = null): void
    {
        $this->assertStream($inputStream, 'input');
        $this->assertStream($outputStream, 'output');

        $sodiumKey = $this->deriveSodiumKey($key);
        $header = $this->safeBase64Decode($headerBase64, 'header');

        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $sodiumKey);
            $readSize = self::STREAM_CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

            while (! feof($inputStream)) {
                $chunk = fread($inputStream, $readSize);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                [$decrypted, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);

                if ($decrypted === false) {
                    throw new RuntimeException('Stream decrypt ล้มเหลว');
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
    //  Utility / Core
    // ═══════════════════════════════════════════════════════════

    public function autoDecrypt(string $encrypted, ?string $key = null): mixed
    {
        $envelope = $this->decodeEnvelope($encrypted);
        $cipher = $envelope['cipher'] ?? 'gcm';

        return match ($cipher) {
            'gcm', 'gcm-aad' => $this->decrypt($encrypted, $key),
            'cbc' => $this->decryptCbc($encrypted, $key),
            'xchacha20' => $this->decryptSodium($encrypted, $key),
            'det-gcm' => $this->decryptDeterministic($encrypted, $key),
            'pbkdf2-gcm' => throw new RuntimeException('pbkdf2-gcm ต้องใช้ decryptWithPassword()'),
            'sealed' => throw new RuntimeException('sealed ต้องใช้ sealDecrypt()'),
            default => throw new RuntimeException("Unknown cipher: {$cipher}"),
        };
    }

    public function reEncrypt(string $encrypted, string $oldKey, string $newKey): string
    {
        $data = $this->autoDecrypt($encrypted, $oldKey);

        return $this->encrypt($data, $newKey);
    }

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

    public function generateKey(): string
    {
        return 'base64:'.self::encode(random_bytes(self::AES_KEY_LENGTH));
    }

    public function base64UrlEncode(string $data): string
    {
        return self::encodeUrlSafe($data);
    }

    public function base64UrlDecode(string $data): string
    {
        return self::decodeUrlSafe($data);
    }

    public function getAvailableCiphers(): array
    {
        return openssl_get_cipher_methods();
    }

    // ─── Private ────────────────────────────────────────────────

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
            throw new RuntimeException('AES-256-GCM encrypt ล้มเหลว');
        }

        return $this->encodeEnvelope([
            'v' => self::VERSION,
            'cipher' => 'gcm',
            'iv' => self::encode($iv),
            'tag' => self::encode($tag),
            'data' => self::encode($ciphertext),
        ]);
    }

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
            throw new RuntimeException('AES-256-GCM decrypt ล้มเหลว');
        }

        return $plaintext;
    }

    private function resolveKey(?string $key): string
    {
        $resolved = $key !== null ? $this->parseKey($key) : $this->appKey;

        if ($resolved === '') {
            throw new InvalidArgumentException('Encryption key is required');
        }

        return $resolved;
    }

    private function deriveSodiumKey(?string $key): string
    {
        $resolved = $this->resolveKey($key);

        if (strlen($resolved) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            return hash('sha256', $resolved, true);
        }

        return $resolved;
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

    private function encodeEnvelope(array $payload): string
    {
        return self::encodeb64((json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
    }

    private function decodeEnvelope(string $encrypted): array
    {
        $json = self::decodeb64($encrypted);
        if ($json === '' || $json === false) {
            throw new RuntimeException('Envelope base64 decode ล้มเหลว');
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Envelope JSON decode ล้มเหลว');
        }

        return $payload;
    }

    private function assertEnvelopeFields(array $envelope, array $required): void
    {
        $missing = array_diff($required, array_keys($envelope));
        if ($missing !== []) {
            throw new InvalidArgumentException('Encrypted envelope missing fields: '.implode(', ', $missing));
        }
    }

    private function safeBase64Decode(string $data, string $field): string
    {
        $decoded = self::decodeb64($data);
        if ($decoded === false) {
            throw new InvalidArgumentException("Invalid base64 in field: {$field}");
        }

        return $decoded;
    }

    private function assertStream($stream, string $name): void
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException("{$name} ต้องเป็น valid stream resource");
        }
    }
}
