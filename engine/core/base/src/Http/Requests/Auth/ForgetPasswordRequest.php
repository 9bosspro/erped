<?php

declare(strict_types=1);

namespace Core\Base\Http\Requests\Auth;

use Core\Base\Traits\ApiResponseTraits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ForgetPasswordRequest — Validate คำขอ reset รหัสผ่าน (กรณีลืมรหัสผ่าน)
 *
 * ตรวจสอบว่า email มีอยู่ในระบบก่อนดำเนินการ
 * ป้องกัน token generation สำหรับ email ที่ไม่มีในระบบ
 */
class ForgetPasswordRequest extends FormRequest
{
    use ApiResponseTraits;

    /**
     * ตรวจสอบสิทธิ์การเข้าถึง
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * กฎ validation
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:50|exists:users,email',
        ];
    }

    /**
     * ข้อความ error ภาษาไทย
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.max' => 'อีเมลต้องไม่เกิน 50 ตัวอักษร',
            'email.exists' => 'ไม่พบผู้ใช้ที่มีอีเมลนี้',
        ];
    }

    /**
     * จัดการ validation failure — คืน JSON สำหรับ API, redirect สำหรับ Web
     */
    public function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                $this->sendError('Validation Error', 422, $validator->errors()->toArray()),
            );
        }

        parent::failedValidation($validator);
    }
}
