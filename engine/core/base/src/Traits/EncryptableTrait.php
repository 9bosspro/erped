<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Contracts\Encryption\DecryptException;

/**
 * EncryptableTrait — เข้า/ถอดรหัส Model attributes อัตโนมัติ (Laravel Encrypter)
 *
 * การใช้งาน:
 * ```php
 * class UserSalary extends Model
 * {
 *     use EncryptableTrait;
 *
 *     protected array $encryptable = ['payroll', 'bank_account'];
 * }
 * ```
 *
 * หมายเหตุ: ใช้ Laravel's `encrypt()` / `decrypt()` ซึ่งอิง APP_KEY
 * ถ้า APP_KEY เปลี่ยน ข้อมูลที่เข้ารหัสไว้จะถอดรหัสไม่ได้ — ต้อง re-encrypt ก่อน rotate key
 */
trait EncryptableTrait
{
    /**
     * ถอดรหัส attribute ถ้าอยู่ใน $encryptable list
     *
     * @param  string  $key  ชื่อ attribute
     * @return mixed ค่าที่ถอดรหัสแล้ว หรือค่าเดิมถ้าถอดรหัสไม่ได้
     */
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (
            ! empty($this->encryptable)
            && in_array($key, $this->encryptable, strict: true)
            && $value !== null
            && $value !== ''
        ) {
            try {
                $value = decrypt($value);
            } catch (DecryptException) {
                // คืนค่าเดิมถ้า decrypt ล้มเหลว (เช่น ข้อมูลเก่าที่ยังไม่ได้เข้ารหัส)
            }
        }

        return $value;
    }

    /**
     * เข้ารหัส attribute ก่อนบันทึก ถ้าอยู่ใน $encryptable list
     * ข้าม null — ไม่เข้ารหัสค่าที่เป็น null
     *
     * @param  string  $key  ชื่อ attribute
     * @param  mixed  $value  ค่าที่ต้องการบันทึก
     */
    public function setAttribute($key, $value): mixed
    {
        if (
            ! empty($this->encryptable)
            && in_array($key, $this->encryptable, strict: true)
            && $value !== null
        ) {
            $value = encrypt($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * ถอดรหัส attributes ที่ถูกเข้ารหัสทั้งหมด เมื่อแปลง Model เป็น array
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        if (empty($this->encryptable)) {
            return $attributes;
        }

        foreach ($this->encryptable as $key) {
            if (isset($attributes[$key]) && $attributes[$key] !== '') {
                try {
                    $attributes[$key] = decrypt($attributes[$key]);
                } catch (DecryptException) {
                    // คงค่าเดิมไว้ถ้า decrypt ล้มเหลว
                }
            }
        }

        return $attributes;
    }
}
