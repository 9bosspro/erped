<?php

namespace Core\Base\Http\Requests\Auth;

use Core\Base\Traits\ApiResponseTraits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SignupRegisterRequest extends FormRequest
{
    use ApiResponseTraits;

    //
    /**
     * ตรวจสอบว่าผู้ใช้มีสิทธิ์ทำรายการนี้หรือไม่
     * (เปลี่ยนเป็น true เพื่อให้ใช้งานได้)
     */
    public function authorize(): bool
    {
        return true;
        // return false;
    }

    /**
     * กำหนดกฎ (Rules) การตรวจสอบ
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:50|unique:users,email',  // |unique:users,email
        ];
    }

    /**
     * กำหนดข้อความ Error (Custom Messages) เป็นภาษาไทย
     */
    public function messages(): array
    {
        return [
            'email.required' => 'กรุณากรอกอีเมล',
            'email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.max' => 'อีเมลต้องมีความยาวไม่เกิน 50 ตัวอักษร',
            'email.unique' => 'อีเมลนี้มีผู้ใช้แล้ว',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        // 1. ถ้าเป็นการเรียกผ่าน API หรือ Request ที่ต้องการ JSON
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                $this->sendError('Validation Error', 422, $validator->errors()->toArray()),
            );
        }

        // 2. ถ้าเป็นการเรียกผ่าน Web ทั่วไป (เช่น หน้า Form Login บน Browser)
        // ให้มันโยน ValidationException ปกติ เพื่อให้ Laravel พากลับไปหน้าเดิมพร้อม Error (Redirect back with errors)
        parent::failedValidation($validator);
    }
}
