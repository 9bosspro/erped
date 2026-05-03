<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use Exception;
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
    /** @var array<string, string> Cache for KDF domain contexts */
    protected static array $contextCache = [];

    // ─── Base64 Encoding/Decoding (Core) ───────────────────────────────────────

    /**
     * Encode binary string to Base64 (Standard Original with Padding)
     * Matches Laravel's base64 encoding for key storage.
     */
    public static function encode(string $rawBinary): string
    {
        return \sodium_bin2base64($rawBinary, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * Decode Base64 string to binary (Standard Original with Padding)
     */
    public static function decode(string $base64): string|false
    {
        try {
            return \sodium_base642bin($base64, SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            return false;
        }
    }

    /**
     * Encode binary string to Base64 (Compact No Padding)
     * Recommended for internal storage and encrypted tokens.
     */
    public static function encodeb64(string $rawBinary): string
    {
        return \sodium_bin2base64($rawBinary, SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
    }

    /**
     * Decode Base64 string to binary (Compact No Padding)
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
     * Encode binary string to Base64url (URLSafe No Padding)
     */
    public static function encodeUrlSafe(string $rawBinary): string
    {
        return \sodium_bin2base64($rawBinary, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Decode Base64url string to binary (URLSafe No Padding)
     *
     * คืน false เมื่อ input ไม่ถูกต้อง — consistent กับ decodeb64()
     */
    public static function decodeUrlSafe(string $base64Url): string|false
    {
        if (! self::isBase64UrlSafe($base64Url)) {
            return false;
        }
        try {
            return \sodium_base642bin($base64Url, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return false;
        }
    }

    /** @deprecated Use encodeUrlSafe() or encodeb64() */
    public static function b64Encode(string $binary): string
    {
        return self::encodeUrlSafe($binary);
    }

    /**
     * @deprecated Use decodeUrlSafe() or decodeb64()
     */
    public static function b64Decode(string $b64): string|false
    {
        return self::decodeUrlSafe($b64);
    }

    /** @deprecated Use encodeUrlSafe() */
    public static function encodeb64UrlSafe(string $rawBinary): string
    {
        return self::encodeUrlSafe($rawBinary);
    }

    /**
     * @deprecated Use decodeUrlSafe()
     */
    public static function decodeb64UrlSafe(string $base64Url): string|false
    {
        return self::decodeUrlSafe($base64Url);
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
     * สร้าง Master Key แบบสุ่มขนาด 32 bytes (XChaCha20-Poly1305-IETF)
     *
     * @return string raw binary หรือ Base64 No Padding ตาม $useBase64
     */
    public static function generateKeyMaster(bool $useBinary = false): string
    {
        $key = \sodium_crypto_aead_xchacha20poly1305_ietf_keygen();

        return self::encodeKey($key, $useBinary);
    }

    /**
     * แปลง Binary ให้เป็น String (Hex/Base64) เพื่อส่งออก (Output)
     */
    public static function encodeKey(string $binaryData, bool $useBinary = false): string
    {
        return $useBinary
            ? $binaryData
            : self::encodeUrlSafe($binaryData);
    }

    /**
     * แปลง String (Hex/Base64) กลับเป็น Binary เพื่อใช้งาน (Input)
     */
    public static function decodeKey(string $encodedKey): ?string
    {
        try {
            // 1. ลอง Base64Url (Safe for URL, No Padding) — แนะนำ
            if ($urlSafeDecoded = self::decodeUrlSafe($encodedKey)) {
                return $urlSafeDecoded;
            }

            // 2. ลอง Hex
            if (\ctype_xdigit($encodedKey) && (\strlen($encodedKey) % 2 === 0)) {
                $hexDecoded = @\sodium_hex2bin($encodedKey);
                if ($hexDecoded !== false) {
                    return $hexDecoded;
                }
            }

            // 3. ลอง Base64 (Standard Original)
            if (self::isBase64($encodedKey)) {
                $b64Decoded = self::decode($encodedKey);
                if ($b64Decoded !== false) {
                    return $b64Decoded;
                }
            }

            // 4. ลอง Base64 No Padding
            if (self::isValidBase64NoPadding($encodedKey)) {
                $b64Decoded = self::decodeb64($encodedKey);
                if ($b64Decoded !== false) {
                    return $b64Decoded;
                }
            }

            return $encodedKey; // อาจเป็น binary อยู่แล้ว
        } catch (Exception $e) {
            return null;
        }
    }

    public static function resetCache(): void
    {
        self::$contextCache = [];
    }

    /**
     * สร้าง Salt แบบสุ่ม
     *
     * @param  int  $length  ความยาวของ Salt (default: 16 bytes)
     * @return string Salt ในรูปแบบ base64
     */
    public function generateSalt(int $length = SODIUM_CRYPTO_PWHASH_SALTBYTES, bool $useBinary = false): string
    {
        $random = random_bytes(max(1, $length));

        return self::encodeKey($random, $useBinary);
    }

    /**
     * สร้าง Master Key จาก Passphrase และ Saltphrase
     *
     * @param  string  $passphrase  รหัสผ่าน
     * @param  string  $saltphrase  รหัสผ่าน
     * @param  bool  $useBinary  return raw binary
     * @return string raw binary หรือ Base64 No Padding ตาม $useBase64
     */
    public function generateFromPassphrase(string $passphrase, string $saltphrase, bool $useBinary = true): string
    {
        $masterKey = null;
        $salt = null;

        try {
            // ใช้ mb_strlen เพื่อรองรับการตั้งรหัสผ่าน/Salt เป็นอักขระพิเศษหรือภาษาไทย
            if (mb_strlen($passphrase, 'UTF-8') < 20) {
                throw new InvalidArgumentException(
                    'Passphrase ควรยาวอย่างน้อย 20 ตัวอักษร',
                );
            }
            if (mb_strlen($saltphrase, 'UTF-8') < 16) {
                throw new InvalidArgumentException(
                    'Saltphrase ควรยาวอย่างน้อย 16 ตัวอักษร',
                );
            }

            // ใช้ generichash ดึง Salt ออกมา 16 bytes แทน sha256 เพื่อให้เป็น Pure Libsodium
            $salt = sodium_crypto_generichash($saltphrase, '', SODIUM_CRYPTO_PWHASH_SALTBYTES);

            $masterKey = sodium_crypto_pwhash(
                32,
                $passphrase,
                $salt, // ใช้ userId-derived salt
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE, // 👈 แนะนำให้ปรับเป็น Moderate สำหรับสร้างคีย์
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
                SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
            );

            return self::encodeKey($masterKey, $useBinary);
        } finally {
            // ล้างค่าความลับออกจากตัวแปร - สำคัญสำหรับความปลอดภัย
            if (is_string($masterKey)) {
                sodium_memzero($masterKey);
            }
            if (is_string($passphrase)) {
                sodium_memzero($passphrase);
            }
            if (is_string($salt)) {
                sodium_memzero($salt);
            }
        }
    }

    public function verifyFromPassphras(string $inputPassword, string $passphrase, string $saltphrase, bool $useBinary = true): bool
    {
        $derivedKey = $this->generateFromPassphrase($passphrase, $saltphrase, $useBinary);

        // ✅ ใช้ hash_equals() — ป้องกัน timing attack
        return hash_equals($inputPassword, $derivedKey);
    }

    /**
     * เพิ่ม `base64:` prefix ให้กับ raw key
     *
     * @param  string  $key  raw key
     * @return string `base64:` prefixed key
     */
    public function prefixKeyBase64(string $name, string $key): string
    {
        $key = $this->resolveKey($key, 32);
        $slaveSeed = $this->genHashByName($name, $key, 32);

        return 'base64:' . static::encode($slaveSeed);
    }

    /**
     * สร้าง prefixKeyBase64FromPassphrase
     *
     * @param  string  $passphrase  passphrase
     * @param  string  $saltphrase  saltphrase
     * @return string prefixKeyBase64FromPassphrase
     */
    public function genPrefixKeyBase64FromPassphrase(string $passphrase, string $saltphrase): string
    {
        $masterKey = $this->generateFromPassphrase($passphrase, $saltphrase, true);
        try {
            return 'base64:' . static::encode($masterKey);
        } finally {
            // ล้างค่าความลับออกจากตัวแปร
            if (is_string($masterKey)) {
                sodium_memzero($masterKey);
            }
            if (is_string($passphrase)) {
                sodium_memzero($passphrase);
            }
        }
    }

    public function parseKey(string $key): string
    {
        $rawKey = $key;

        // 1. ถอดรหัสถ้ามี Prefix
        if (str_starts_with($key, 'base64:')) {
            $rawBase64 = substr($key, 7);

            try {
                // พารามิเตอร์ที่ 3 (ignore) ใส่ string ว่างเพื่อบังคับให้ Strict ที่สุด
                $rawKey = \sodium_base642bin($rawBase64, SODIUM_BASE64_VARIANT_ORIGINAL, '');
            } catch (SodiumException $e) {
                // โยน Exception ออกไปเลย เพื่อให้แอพล่มทันทีตอน Boot ระบบ ไม่ปล่อยให้ทำงานต่อ
                throw new RuntimeException('Master key is corrupted or not a valid base64 string.');
            }
        }

        // 2. ตรวจสอบความยาวแบบระดับไบต์ (ใช้ '8bit' เสมอสำหรับข้อมูล Binary)
        if (mb_strlen($rawKey, '8bit') !== 32) {
            // ล้างค่าทิ้งก่อนโยน Error เพื่อความปลอดภัย
            \sodium_memzero($rawKey);
            throw new RuntimeException('Master key must be exactly 32 bytes long.');
        }

        return $rawKey;
    }

    /**
     * Fast Base64 JSON encode for non-critical data
     *
     * @param  array<mixed>  $payload
     */
    public function encodeEnvelopes(array $payload): string
    {
        return self::encodeb64(\json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
     * Resolve key — decode Base64 ถ้าระบุ หรือใช้ getAppKey() เป็น default
     * รองรับ No Padding (encodeb64) และ Standard Base64 (encode) อัตโนมัติ
     *
     * @param  string|null  $keyBase64  กุญแจ Base64 (No Padding หรือ Standard) หรือ null เพื่อใช้ appKey
     * @param  int  $length  ขนาดกุญแจที่คาดหวัง (bytes)
     * @return string raw binary key
     *
     * @throws InvalidArgumentException เมื่อขนาดกุญแจไม่ถูกต้อง
     */
    public function resolveKey(?string $keyBase64, int $length = SODIUM_CRYPTO_SECRETBOX_KEYBYTES): ?string
    {
        try {
            // 1. ถ้าว่าง ให้ใช้ Default App Key
            if (empty($keyBase64)) {
                $key = $this->getAppKey();
                if ($key === '') {
                    throw new InvalidArgumentException('Encryption key is required but appKey is empty.');
                }

                return $key;
            }

            //   $keyBase64 = $this->parseKey($keyBase64);

            // 2. ลองถอดรหัสและเช็คความยาว
            if ($decoded = self::decodeKey($keyBase64)) {
                if (\strlen($decoded) === $length) {
                    return $decoded;
                }
            }

            // 3. กรณีสุดท้าย: ถ้าความยาวมันเป๊ะตั้งแต่แรก (เป็น Binary ดิบ)
            if (\strlen($keyBase64) === $length) {
                return $keyBase64;
            }

            // ถ้าไม่เข้าเงื่อนไขเลย ให้โยน Exception ทันที ไม่ควรเดาต่อ
            throw new InvalidArgumentException(
                "Invalid key: The provided string is not a valid Hex, Base64, or Binary key of {$length} bytes.",
            );
        } catch (Exception $e) {
            return null;
        }
    }

    //
    /**
     * Generate a derived key from a master key and a name using HKDF-SHA256.
     *
     * @param  string  $name  The name to derive the key from.
     * @param  string  $key  The master key (must be 32 bytes).
     * @param  int  $length  The desired length of the derived key (default: 32 bytes).
     * @param  int  $subkeyId  The subkey ID (default: 1).
     * @return string The derived key.
     *
     * @throws InvalidArgumentException If the name, key, or length is invalid.
     */
    public function genHashByName(
        string $name,
        string $key,
        int $length = 32,
        int $subkeyId = 1,
    ): string {

        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if (strlen($name) > 128) {
            throw new InvalidArgumentException(
                'Name too long',
            );
        }

        if ($key === '') {
            throw new InvalidArgumentException('Key is required.');
        }

        if ($length <= 0) {
            throw new InvalidArgumentException('Invalid length.');
        }
        if ($length < SODIUM_CRYPTO_KDF_BYTES_MIN || $length > SODIUM_CRYPTO_KDF_BYTES_MAX) {
            throw new InvalidArgumentException('Length must be between ' . SODIUM_CRYPTO_KDF_BYTES_MIN . ' and ' . SODIUM_CRYPTO_KDF_BYTES_MAX);
        }
        //


        // ภายในฟังก์ชัน:
        $cacheKey = $name; // . ':' . $length . ':' . $subkeyId; // หรือแค่ $name ถ้า length คงที่
        if (! isset(self::$contextCache[$cacheKey])) {
            $fullContext = \sodium_crypto_generichash('KDFCTX-v1:' . $name, '', 24);
            self::$contextCache[$cacheKey] = \substr($fullContext, 0, 8);
        }
        $context = self::$contextCache[$cacheKey];
        //
        $key = $this->resolveKey($key, 32); //SODIUM_CRYPTO_SECRETBOX_KEYBYTES
        if ($key === null) {
            throw new InvalidArgumentException('Key resolve failure or null.');
        }

        /*   if (strlen($key) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new InvalidArgumentException(
                'Key must be 32 bytes.',
            );
        }
        */
        if ($subkeyId < 0 || $subkeyId > PHP_INT_MAX) {
            throw new InvalidArgumentException('Invalid subkey ID');
        }

        // แปลงชื่อยาว → context 8 bytes
        // Note: sodium_crypto_generichash ต้องการ output ขั้นต่ำ 16 bytes
        // เราจะสร้าง 16 bytes แล้วตัดเอา 8 bytes แรกมาใช้เป็น context
        /*   $fullContext = \sodium_crypto_generichash(
            $name,
            '',
            16
        );
        $context = \substr($fullContext, 0, SODIUM_CRYPTO_KDF_CONTEXTBYTES); */

        return \sodium_crypto_kdf_derive_from_key(
            $length,
            $subkeyId,
            $context,
            $key,
        );
    }

    /**
     * คืนค่า JSON prefix สำหรับ serialization
     * (มองหา constant JSON_PREFIX ใน class ที่ใช้ หรือใช้ default '$')
     */
    protected function getJsonPrefix(): string
    {
        $value = \defined('static::JSON_PREFIX') ? \constant('static::JSON_PREFIX') : '$';

        return is_scalar($value) ? (string) $value : '$';
    }
}
