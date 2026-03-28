<?php

namespace Core\Base\Http\Requests\Auth;

use Core\Base\Traits\ApiResponseTraits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
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
            'name_th' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * กำหนดข้อความ Error (Custom Messages) เป็นภาษาไทย
     */
    public function messages(): array
    {
        return [
            'name_th.required' => 'กรุณากรอกชื่อภาษาไทย',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกับยืนยันรหัสผ่าน',
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
