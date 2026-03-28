<?php

declare(strict_types=1);

namespace Core\Base\Http\Requests\Auth;

use Core\Base\Traits\ApiResponseTraits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ResetPasswordRequest — Validate รหัสผ่านใหม่สำหรับ reset
 *
 * JWT token ตรวจสอบที่ PasswordResetService ไม่ใช่ที่นี่
 * FormRequest รับผิดชอบเฉพาะ password format validation เท่านั้น
 */
class ResetPasswordRequest extends FormRequest
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
            'password' => 'required|string|min:8|confirmed',
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
            'password.required' => 'กรุณากรอกรหัสผ่านใหม่',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
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
