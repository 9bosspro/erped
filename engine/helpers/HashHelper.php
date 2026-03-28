<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Crypto Global Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชัน global สำหรับ Hash, HMAC, และ Password
| delegate ไปยัง HashHelper และ PasswordHasher (canonical layer)
|
|  HashHelper    → app('core.crypto.hash')
|  PasswordHasher→ app('core.crypto.password')
|
*/

if (! function_exists('encrypt_do_hash_salt')) {
    /**
     * สร้าง hash แบบ one-way ด้วย double salt (SHA256 + SHA3-256 layered)
     *
     * ต้องกำหนด CRYPTO_HASH_SALT_KEY1 และ CRYPTO_HASH_SALT_KEY2 ใน .env
     *
     * @param  string  $input_pass  ข้อความที่ต้องการ hash
     * @return string hash string (SHA3-256 hex) หรือ empty string ถ้า input ว่าง
     *
     * @throws RuntimeException ถ้าไม่ได้กำหนด HASH_SALT keys ใน production
     */
    function encrypt_do_hash_salt(string $input_pass): string
    {
        return app('core.crypto.hash')->hashWithDoubleSalt($input_pass);
    }
}

if (! function_exists('sig_hash_sign')) {
    /**
     * สร้าง signature hash จากข้อมูล (รองรับหลาย algorithm)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการสร้าง signature (array → json_encode อัตโนมัติ)
     * @param  bool  $hash  true = ใช้ double-salt hash, false = ใช้ standard hash
     * @param  string  $hashs  hash algorithm (default: sha3-256)
     * @return string signature hash
     */
    function sig_hash_sign(mixed $data = '', bool $hash = true, string $hashs = 'sha3-256'): string
    {
        return app('core.crypto.hash')->signatureHash($data, $hash, $hashs);
    }
}

if (! function_exists('sig_hash_verify')) {
    /**
     * ตรวจสอบ signature hash (timing-safe comparison)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  string  $signature  signature ที่ต้องการเปรียบเทียบ
     * @param  bool  $hash  ใช้ double-salt hash หรือไม่
     * @param  string  $hashs  algorithm ที่ใช้
     * @return bool true ถ้า signature ตรงกัน
     */
    function sig_hash_verify(
        mixed $data = '',
        string $signature = '',
        bool $hash = true,
        string $hashs = 'sha3-256',
    ): bool {
        return app('core.crypto.hash')->verifySignatureHash($data, $signature, $hash, $hashs);
    }
}

if (! function_exists('pass_secure_hash')) {
    /**
     * สร้าง password hash แบบปลอดภัย (Argon2id)
     *
     * @param  string  $password  รหัสผ่านที่ต้องการ hash
     * @param  bool  $preSalt  true = hash ด้วย double-salt ก่อน Argon2id (เพิ่มความปลอดภัย)
     * @return string hashed password หรือ empty string ถ้า input ว่าง
     */
    function pass_secure_hash(string $password, bool $preSalt = false): string
    {
        if ($password === '') {
            return '';
        }

        if ($preSalt) {
            $password = app('core.crypto.hash')->hashWithDoubleSalt($password);

            if ($password === '') {
                return '';
            }
        }

        return app('core.crypto.password')->hash($password);
    }
}

if (! function_exists('pass_secure_verify')) {
    /**
     * ตรวจสอบ password กับ hash ที่เก็บไว้ในฐานข้อมูล
     *
     * @param  string  $password  รหัสผ่านที่ต้องการตรวจสอบ
     * @param  string  $hashedPassword  hash ที่เก็บไว้ในฐานข้อมูล
     * @param  bool  $preSalt  true = hash ด้วย double-salt ก่อนเปรียบเทียบ
     * @return bool true ถ้าตรงกัน
     */
    function pass_secure_verify(string $password, string $hashedPassword, bool $preSalt = false): bool
    {
        if ($password === '' || $hashedPassword === '') {
            return false;
        }

        if ($preSalt) {
            $password = app('core.crypto.hash')->hashWithDoubleSalt($password);

            if ($password === '') {
                return false;
            }
        }

        return app('core.crypto.password')->verify($password, $hashedPassword);
    }
}
