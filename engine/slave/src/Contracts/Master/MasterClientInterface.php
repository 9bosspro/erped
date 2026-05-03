<?php

declare(strict_types=1);

namespace Slave\Contracts\Master;

/**
 * MasterClientInterface — สัญญาสำหรับ HTTP client ที่ติดต่อกับ Master Server
 *
 * การแยก interface ออกจาก implementation ช่วยให้:
 *  - mock ใน test ได้ง่าย
 *  - swap implementation ได้โดยไม่แก้ caller
 *  - PHPStan/IDE เข้าใจ type ครบถ้วน
 */
interface MasterClientInterface
{
    /**
     * ส่ง GET request ไปยัง Master API
     *
     * @param  array<string, mixed>  $query  query string parameters
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array;

    /**
     * ส่ง POST request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data  request body (JSON)
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array;

    /**
     * ตรวจสอบว่า Master Server ตอบสนองได้ปกติหรือไม่
     */
    public function ping(): bool;

    /**
     * คืนค่า base URL ของ Master Server
     */
    public function getBaseUrl(): string;
}
