<?php

declare(strict_types=1);

namespace Core\Base\Services\Crypto;

use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\PasswordHasher;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * HmacService — Facade Service ที่รวม HMAC, Hash, Password และ Random Generation
 *
 * ═══════════════════════════════════════════════════════════════
 *  สถาปัตยกรรม: Thin Delegation Layer
 * ═══════════════════════════════════════════════════════════════
 *
 * Service นี้ **delegate** ไปยัง Helper layer ที่เป็น canonical source of truth:
 *  - HMAC / Hash logic      → HashHelper       (core.crypto.hash)
 *  - Password hashing logic → PasswordHasher   (core.crypto.password)
 *
 * เหตุผลที่ยังคง Service นี้ไว้:
 *  - Backward compatibility กับ global helper functions (encrypt_do_hash_salt, sig_hash_sign ฯลฯ)
 *  - รวม Random Generation (UUID, random string) ที่ไม่เข้า scope ของ Helper ใด
 *  - สะดวกสำหรับ inject ผ่าน DI เมื่อต้องการหลาย capability พร้อมกัน
 *
 * ⚠️ สำหรับโค้ดใหม่ ให้ inject HashHelper / PasswordHasher โดยตรง
 *    แทนการใช้ HmacService — เพื่อ explicit dependency ที่ชัดเจน
 *
 * Usage:
 *  - ผ่าน DI: HmacService $hmac
 *  - ผ่าน container: app('core.crypto.hmac')
 */
class HmacService
{
    public function __construct(
        private readonly HashHelper $hashHelper,
        private readonly PasswordHasher $passwordHasher,
    ) {}

    // ─── HMAC Sign / Verify ─────────────────────────────────────

    /**
     * สร้าง HMAC-SHA256 signature จากข้อมูล
     *
     * @param  string|array  $data  ข้อมูลที่ต้องการ sign (array จะถูก json_encode อัตโนมัติ)
     * @param  string|null  $key  กุญแจสำหรับ HMAC (null = ใช้ APP_KEY)
     * @param  bool  $binary  true = คืน raw binary แทน hex string
     * @return string HMAC-SHA256 signature (hex หรือ binary)
     */
    public function sign(string|array $data, ?string $key = null, bool $binary = false): string
    {
        return $this->hashHelper->hmacSign($data, $key, 'sha256', $binary);
    }

    /**
     * ตรวจสอบ HMAC-SHA256 signature (timing-safe)
     *
     * @param  string|array  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  string  $signature  signature ที่ต้องการเปรียบเทียบ
     * @param  string|null  $key  กุญแจ (null = ใช้ APP_KEY)
     * @param  bool  $binary  true = เปรียบเทียบแบบ raw binary
     * @return bool true ถ้า signature ถูกต้อง
     */
    public function verify(string|array $data, string $signature, ?string $key = null, bool $binary = false): bool
    {
        return $this->hashHelper->hmacVerify($data, $signature, $key, 'sha256', $binary);
    }

    // ─── Custom Hash (Double Salt) ──────────────────────────────

    /**
     * สร้าง hash แบบ one-way ด้วย double salt (SHA256 + SHA3-256 layered)
     *
     * @param  string  $input  ข้อความที่ต้องการ hash (empty = คืน empty string)
     * @return string hash string (SHA3-256 hex) หรือ empty string
     *
     * @throws RuntimeException ถ้าไม่ได้กำหนด keys ใน production
     */
    public function hashWithDoubleSalt(string $input): string
    {
        return $this->hashHelper->hashWithDoubleSalt($input);
    }

    /**
     * สร้าง signature hash จากข้อมูล (รองรับหลาย algorithm)
     *
     * @param  mixed  $data  ข้อมูล (non-string จะถูก json_encode อัตโนมัติ)
     * @param  bool  $useDoubleSalt  true = ใช้ double-salt hash, false = ใช้ standard hash
     * @param  string  $algorithm  hash algorithm สำหรับ standard mode (default: sha3-256)
     * @return string signature hash string
     */
    public function signatureHash(mixed $data = '', bool $useDoubleSalt = true, string $algorithm = 'sha3-256'): string
    {
        return $this->hashHelper->signatureHash($data, $useDoubleSalt, $algorithm);
    }

    /**
     * ตรวจสอบ signature hash (timing-safe comparison)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  string  $signature  signature ที่ต้องการเปรียบเทียบ
     * @param  bool  $useDoubleSalt  ใช้ double-salt hash หรือไม่
     * @param  string  $algorithm  hash algorithm สำหรับ standard mode
     * @return bool true ถ้า signature ตรงกัน
     */
    public function verifySignatureHash(mixed $data = '', string $signature = '', bool $useDoubleSalt = true, string $algorithm = 'sha3-256'): bool
    {
        return $this->hashHelper->verifySignatureHash($data, $signature, $useDoubleSalt, $algorithm);
    }

    // ─── Password Hashing (delegate → PasswordHasher) ───────────

    /**
     * สร้าง password hash แบบปลอดภัย
     *
     * @param  string  $password  รหัสผ่านที่ต้องการ hash (empty = คืน empty string)
     * @param  bool  $preSalt  true = hash ด้วย double-salt ก่อนส่งเข้า hash (เพิ่มความปลอดภัย)
     * @return string hash string หรือ empty string
     */
    public function hashPassword(string $password, bool $preSalt = false): string
    {
        if ($password === '') {
            return '';
        }

        if ($preSalt) {
            $password = $this->hashHelper->hashWithDoubleSalt($password);

            if ($password === '') {
                return '';
            }
        }

        return $this->passwordHasher->hash($password);
    }

    /**
     * ตรวจสอบ password กับ hash ที่เก็บไว้
     *
     * @param  string  $password  รหัสผ่านที่ต้องการตรวจสอบ
     * @param  string  $hashedPassword  hash ที่เก็บไว้ในฐานข้อมูล
     * @param  bool  $preSalt  true = hash ด้วย double-salt ก่อนเปรียบเทียบ
     * @return bool true ถ้ารหัสผ่านตรงกัน
     */
    public function verifyPassword(string $password, string $hashedPassword, bool $preSalt = false): bool
    {
        if ($password === '' || $hashedPassword === '') {
            return false;
        }

        if ($preSalt) {
            $password = $this->hashHelper->hashWithDoubleSalt($password);

            if ($password === '') {
                return false;
            }
        }

        return $this->passwordHasher->verify($password, $hashedPassword);
    }

    // ─── Random Generation ──────────────────────────────────────

    /**
     * สร้าง unique ID (base-36, cryptographically random)
     *
     * @param  int  $length  ความยาวของ ID ที่ต้องการ
     * @return string random ID string
     */
    public function generateUniqueId(int $length = 32): string
    {
        $bytes = random_bytes($length);

        return substr(base_convert(bin2hex($bytes), 16, 36), 0, $length);
    }

    /**
     * สร้าง random string ตาม character set ที่กำหนด
     *
     * ใช้ random_int() เพื่อความปลอดภัยระดับ cryptographic
     *
     * @param  int  $length  ความยาวของ string
     * @param  int  $count  จำนวน string ที่ต้องการ (1 = คืน string, >1 = คืน array)
     * @param  string  $characters  ชนิดตัวอักษร (comma-separated)
     * @return string|string[] string เดี่ยว หรือ array ของ strings
     */
    public function randomString(
        int $length = 32,
        int $count = 1,
        string $characters = 'numbers,lower_case,upper_case,extra_symbols',
    ): string|array {
        $symbols = [
            'lower_case' => 'abcdefghijklmnopqrstuvwxyz',
            'upper_case' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numbers' => '1234567890',
            'numbers_th' => '๑๒๓๔๕๖๗๘๙๐',
            'char_th' => 'กขฃคฅฆงจฉชซฌญฎฏฐฑฒณดตถทธนบปผฝพฟภมยรลวศษสหฬอฮ',
            'special_symbols' => '!?~@#-_+<>[]{}',
            'extra_symbols' => '!?@#%[]{}',
            'extra_password' => '!@#%()_+[]{}?$*',
            'extra_password_fix' => '!@#[]{}$',
        ];

        mb_internal_encoding('utf-8');

        $usedSymbols = '';

        foreach (explode(',', $characters) as $type) {
            $type = trim($type);

            if (isset($symbols[$type])) {
                $usedSymbols .= $symbols[$type];
            }
        }

        if ($usedSymbols === '') {
            $usedSymbols = $symbols['lower_case'] . $symbols['upper_case'] . $symbols['numbers'];
        }

        $symbolsLength = mb_strlen($usedSymbols, 'utf-8') - 1;
        $passwords = [];

        for ($p = 0; $p < $count; $p++) {
            $pass = '';

            for ($i = 0; $i < $length; $i++) {
                $n = random_int(0, $symbolsLength);
                $pass .= mb_substr($usedSymbols, $n, 1, 'utf-8');
            }

            $passwords[] = $pass;
        }

        return $count === 1 ? $passwords[0] : $passwords;
    }

    /**
     * สร้าง UUID ตาม version ที่กำหนด
     *
     * @param  int  $version  UUID version (default 4, รองรับ 1-7)
     * @param  bool  $includeDash  true = มี dash
     * @return string UUID string
     *
     * @throws RuntimeException ถ้า version ไม่รองรับ
     */
    public function generateId(int $version = 4, bool $includeDash = true): string
    {
        try {
            $uuid = match ($version) {
                1 => Uuid::uuid1(),
                2 => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON),
                3 => Uuid::uuid3(Uuid::NAMESPACE_DNS, php_uname('n')),
                5 => Uuid::uuid5(Uuid::NAMESPACE_DNS, php_uname('n')),
                6 => Uuid::uuid6(),
                7 => Uuid::uuid7(),
                default => Uuid::uuid7(),
            };
        } catch (Throwable $e) {
            throw new RuntimeException("UUID v{$version} generation failed: {$e->getMessage()}", previous: $e);
        }

        $string = $uuid->toString();

        return $includeDash ? $string : str_replace('-', '', $string);
    }

    /**
     * รายการ hash algorithms ที่ระบบรองรับ
     *
     * @return string[]
     */
    public function getAvailableAlgorithms(): array
    {
        return $this->hashHelper->getAvailableAlgorithms();
    }
}
