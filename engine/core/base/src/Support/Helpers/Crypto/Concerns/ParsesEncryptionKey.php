<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SodiumException;

/**
 * ParsesEncryptionKey — Trait สำหรับ parse และ encode/decode encryption key
 *
 * ═══════════════════════════════════════════════════════════════
 *  ความสามารถหลัก:
 * ═══════════════════════════════════════════════════════════════
 *  - parseKey()        — decode `base64:` prefix (Laravel convention)
 *  - prefixKeyBase64() — เพิ่ม `base64:` prefix ให้กับ raw key
 *  - encode/decode     — Sodium Base64 (ORIGINAL variant) — static
 *  - encodeUrlSafe     — Sodium Base64url (URLSAFE_NO_PADDING) — static
 *  - b64Encode/b64Decode — Standard base64url (RFC 4648) — static
 *
 * ใช้ร่วมกันระหว่าง HashHelper, EncryptionHelper, SodiumHelper
 * เพื่อหลีกเลี่ยง duplicate implementation
 */
trait ParsesEncryptionKey
{
    // ─── Aliases (encode/decode) ────────────────────────────────────────────────

    /**
     * Alias สำหรับ encodeb64() — ใช้ใน EncryptionHelper, RsaHelper, SodiumHelper
     */
    public static function encode(string $rawBinary): string
    {
        return static::encodeb64($rawBinary);
    }

    /**
     * Alias สำหรับ decodeb64() — คืน false เมื่อ decode ล้มเหลว
     */
    public static function decode(string $base64): string|false
    {
        try {
            return static::decodeb64($base64);
        } catch (SodiumException) {
            return false;
        }
    }

    /**
     * Alias สำหรับ encodeb64UrlSafe()
     */
    public static function encodeUrlSafe(string $rawBinary): string
    {
        return static::encodeb64UrlSafe($rawBinary);
    }

    /**
     * Alias สำหรับ decodeb64UrlSafe()
     */
    public static function decodeUrlSafe(string $base64Url): string
    {
        return static::decodeb64UrlSafe($base64Url);
    }

    // ─── Base64 Encode/Decode ───────────────────────────────────────────────────

    /**
     * Encode binary เป็น Base64 (SODIUM_BASE64_VARIANT_ORIGINAL)
     *
     * @param  string  $rawBinary  raw bytes
     * @return string Base64 string
     */
    public static function encodeb64(string $rawBinary): string
    {
        return \sodium_bin2base64($rawBinary, SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
    }

    /**
     * Decode Base64 เป็น binary (SODIUM_BASE64_VARIANT_ORIGINAL)
     *
     * @param  string  $base64  Base64 string
     * @return string raw bytes
     */
    public static function decodeb64(string $base64): string|false
    {
        if (! self::isValidBase64NoPadding($base64)) {
            return false;
        }
        try {
            return \sodium_base642bin($base64, SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
        } catch (SodiumException) {
            return false;
        }
    }

    /**
     * Encode binary เป็น Base64url (SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)
     *
     * @param  string  $rawBinary  raw bytes
     * @return string Base64url string (ปลอดภัยสำหรับ URL/Header)
     */
    public static function encodeb64UrlSafe(string $rawBinary): string
    {
        return \sodium_bin2base64($rawBinary, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Decode Base64url เป็น binary (SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)
     *
     * @param  string  $base64Url  Base64url string
     * @return string raw bytes
     */
    public static function decodeb64UrlSafe(string $base64Url): string|false
    {
        if (! self::isBase64UrlSafe($base64Url)) {
            throw new RuntimeException('Invalid base64url');
        }
        try {
            return \sodium_base642bin($base64Url, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            // throw new RuntimeException("Invalid base64url encoding");
            return false;
        }
    }

    /**
     * Encode binary เป็น base64url string (RFC 4648, ไม่มี padding `=`)
     *
     * @param  string  $binary  raw bytes
     * @return string base64url string (ไม่มี +, /, =)
     */
    public static function b64Encode(string $binary): string
    {
        return static::encodeb64UrlSafe($binary);
    }

    /**
     * Decode base64url string เป็น binary (RFC 4648)
     *
     * @param  string  $b64  base64url string
     * @return string raw bytes
     */
    public static function b64Decode(string $b64): string
    {
        return static::decodeb64UrlSafe($b64);
    }

    // ─── Validation helpers ────────────────────────────────────────────────────

    /**
     * ตรวจสอบว่า string เป็น Base64 ที่ถูกต้อง (SODIUM_BASE64_VARIANT_ORIGINAL)
     *
     * ตรงคู่กับ encode() / decode()
     * Alphabet: A–Z a–z 0–9 + /   พร้อม padding =
     * ความยาวต้องหาร 4 ลงตัว
     *
     * @param  string  $input  string ที่ต้องการตรวจสอบ
     */
    public static function isBase64(string $input): bool
    {
        if ($input === '') {
            return false;
        }

        return \strlen($input) % 4 === 0
            && (bool) \preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $input);
    }

    /**
     * ตรวจสอบว่า string เป็น Base64url ที่ถูกต้อง (SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING)
     *
     * ตรงคู่กับ encodeUrlSafe() / decodeUrlSafe()
     * Alphabet: A–Z a–z 0–9 - _   ไม่มี padding
     *
     * @param  string  $input  string ที่ต้องการตรวจสอบ
     */
    public static function isBase64UrlSafe(string $input): bool
    {
        if ($input === '') {
            return false;
        }

        return (bool) \preg_match('/^[A-Za-z0-9_-]+$/', $input);
    }

    /**
     * ตรวจสอบว่า string เป็น Base64 (ORIGINAL_NO_PADDING) ที่ถูกต้อง
     *
     * ตรงคู่กับ b64Encode() / b64Decode() ซึ่งใช้ SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING
     * Alphabet: A–Z a–z 0–9 + /      ไม่มี padding (=)
     *
     * @param  string  $input  string ที่ต้องการตรวจสอบ
     */
    public static function isValidBase64NoPadding(string $input): bool
    {
        if ($input === '') {
            return false;
        }

        return (bool) \preg_match('/^[A-Za-z0-9+\/]+$/', $input);
    }

    /**
     * Clear sensitive data from memory
     *
     * @param-out string|null $secret
     */
    public static function memzero(string &$secret): void
    {
        if (function_exists('sodium_memzero')) {
            \sodium_memzero($secret);
        } else {
            $secret = str_repeat("\0", strlen($secret));
        }
    }

    /**
     * Validate if a string is a valid JSON
     *
     * @param  bool  $allowEmpty  Allow {}, [], null or empty string
     */
    public static function isJson(?string $value, bool $allowEmpty = false): bool
    {
        if ($value === null || $value === '') {
            return $allowEmpty;
        }

        $value = trim($value);

        if ($allowEmpty && in_array($value, ['{}', '[]', 'null'], true)) {
            return true;
        }

        if (function_exists('json_validate')) {
            return json_validate($value);
        }

        try {
            json_decode($value, false, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * สร้าง Salt แบบสุ่ม
     *
     * @param  int  $length  ความยาวของ Salt (default: 16 bytes)
     * @return string Salt ในรูปแบบ base64
     */
    public function generateSalt(int $length = SODIUM_CRYPTO_PWHASH_SALTBYTES, bool $isBase64 = false, bool $urlSafe = false): string
    {
        $random = random_bytes(max(1, $length));

        return $this->maybeBase64($random, $isBase64, $urlSafe);
    }

    /**
     * เพิ่ม `base64:` prefix ให้กับ raw key
     *
     * @param  string  $key  raw key
     * @return string `base64:` prefixed key
     */
    public function prefixKeyBase64(string $key): string
    {
        return 'base64:'.static::encodeb64($key);
    }

    public function encodeEnvelopes(array $payload): string
    {
        return self::encodeb64((json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
    }

    public function decodeEnvelope(string $encrypted): array
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

    /**
     * คืนค่า default app key — override ในคลาสที่มี $appKey property
     */
    protected function getAppKey(): string
    {
        //  return (string) self::encodeb64(config('core.base::security.key32', ''));
        return '';
    }

    /**
     * คืนค่า JSON prefix สำหรับ serialization
     * (มองหา constant JSON_PREFIX ใน class ที่ใช้ หรือใช้ default '$')
     */
    protected function getJsonPrefix(): string
    {
        return \defined('static::JSON_PREFIX') ? (string) \constant('static::JSON_PREFIX') : '$';
    }

    /**
     * Resolve key — decode Base64 ถ้าระบุ หรือใช้ getAppKey() เริ่มต้น
     * ตรวจสอบขนาดกุญแจให้ถูกต้องสำหรับ Sodium SecretBox (32 bytes)
     *
     * @param  string|null  $keyBase64  กุญแจ Base64 หรือ null เพื่อใช้ default key
     * @return string raw binary key (32 bytes)
     *
     * @throws InvalidArgumentException เมื่อขนาดกุญแจไม่ถูกต้อง
     */
    private function resolveKey(?string $keyBase64, int $length = SODIUM_CRYPTO_SECRETBOX_KEYBYTES): string
    {
        if (empty($keyBase64)) {
            throw new InvalidArgumentException('Key is empty in resolveKey');
        }
        $rawKey32 = $this->decodeb64($keyBase64);
        if ($rawKey32 === false) {
            throw new InvalidArgumentException('Key is not base64 no padding in resolveKey');
        }

        if (\strlen($rawKey32) !== $length) {
            throw new InvalidArgumentException(
                'กุญแจต้องมีขนาด '.$length.' bytes ('.$length.' bytes) '
                    .'— ใช้ generateEncryptionKey() เพื่อสร้างกุญแจที่ถูกต้อง',
            );
        }

        return $rawKey32;
    }

    /**
     * Parse key — รองรับ `base64:xxxxx` prefix เหมือน Laravel
     *
     * @param  string  $keyb64  raw key string (อาจมี `base64:` prefix)
     * @return string decoded key string
     *
     * @throws RuntimeException เมื่อ base64 decode ล้มเหลว
     */
    private function parseKey(string $keyb64): string
    {
        if (str_starts_with($keyb64, 'base64:')) {
            $rawBase64 = substr($keyb64, 7);

            // ใช้ Libsodium เพื่อความปลอดภัยระดับสูงสุด และรองรับ Timing Attack Protection
            // เลือก Variant ให้ตรงกับที่คุณใช้เก็บใน .env (ปกติ Laravelใช้ Original)
            try {
                return \sodium_base642bin($rawBase64, SODIUM_BASE64_VARIANT_ORIGINAL);
            } catch (SodiumException $e) {
                // หากถอดรหัสไม่สำเร็จ ให้จัดการ Error อย่างเหมาะสม
                throw new RuntimeException('Invalid Key Encoding');
            }
        }

        return $keyb64;
    }

    /**
     * Encode data to Base64 or Base64url if needed
     */
    private function maybeBase64(mixed $data, bool $isBase64, bool $urlSafe): string
    {
        if (! $isBase64) {
            return $data;
        }

        return $urlSafe ? self::encodeb64UrlSafe($data) : self::encodeb64($data);
    }
}
