<?php

namespace App\DTOs;

use App\Http\Requests\Settings\PasswordUpdateRequest;

/**
 * วัตถุเก็บข้อมูลสำหรับการอัปเดตรหัสผ่าน (Password Update DTO)
 */
readonly class PasswordUpdateData
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}

    /**
     * สร้าง DTO จาก Form Request
     *
     * @param PasswordUpdateRequest $request
     * @return self
     */
    public static function fromRequest(PasswordUpdateRequest $request): self
    {
        return new self(
            currentPassword: $request->validated('current_password'),
            password: $request->validated('password'),
        );
    }
}
