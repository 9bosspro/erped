<?php

declare(strict_types=1);

namespace Slave\Contracts\Master;

use App\Models\User;

/**
 * BackendAuthServiceInterface — Contract สำหรับ authentication flow ผ่าน Master Server
 *
 * กำหนด API ที่ Controller ต้องการ โดยไม่ผูกกับ implementation ใด
 * ทำให้สามารถ swap หรือ mock ได้ง่ายใน test
 */
interface BackendAuthServiceInterface
{
    /**
     * Login ผ่าน Master Server
     *
     * @return array{success: bool, message: string, user?: User, errors?: array<mixed>}
     */
    public function login(string $email, string $password): array;

    /**
     * ตรวจสอบว่า Master Token ปัจจุบันหมดอายุแล้วหรือไม่
     */
    public function isTokenExpired(): bool;

    /**
     * ดึง Access Token ล่าสุดของ Context ปัจจุบัน
     */
    public function getBackendToken(): ?string;
}
