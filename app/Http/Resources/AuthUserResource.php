<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AuthUserResource — DTO ของ user ที่ปลอดภัยสำหรับส่งให้ Frontend ผ่าน Inertia shared props
 *
 * เปิดเผยเฉพาะ field ที่จำเป็นต่อ UI เพื่อกัน leak ของ field อ่อนไหว
 * (remember_token, password hash ฯลฯ) และทำให้ contract ระหว่าง backend/frontend ชัดเจน
 *
 * @property-read User $resource
 */
final class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id'                  => $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'email_verified_at'   => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled'  => $user->two_factor_confirmed_at !== null,
            'created_at'          => $user->created_at?->toIso8601String(),
            'updated_at'          => $user->updated_at?->toIso8601String(),
        ];
    }
}
