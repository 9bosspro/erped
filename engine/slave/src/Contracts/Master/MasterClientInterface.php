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
     * ส่ง PUT request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data  request body (JSON)
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array;

    /**
     * ส่ง PATCH request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data  request body (JSON)
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array;

    /**
     * ส่ง DELETE request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data  request body (JSON)
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $data = []): array;

    /**
     * ตรวจสอบว่า Master Server ตอบสนองได้ปกติหรือไม่
     */
    public function ping(): bool;

    /**
     * คืนค่า base URL ของ Master Server
     */
    public function getBaseUrl(): string;

    /**
     * ดึงข้อมูล licence (cache 1 ชั่วโมง)
     *
     * @return array<string, mixed>|null
     */
    public function getLicence(): ?array;

    /**
     * ดึงรายการไฟล์จาก Master
     *
     * @return array<string, mixed>
     */
    public function getFiles(): array;

    /**
     * คืนค่า OAuth2 access token (ดึงจาก cache หรือขอใหม่)
     */
    public function getAccessToken(string $scope = ''): string;

    /**
     * คืนค่า JWT access token (ดึงจาก cache หรือขอใหม่)
     */
    public function getAccessTokenJwt(string $scope = ''): string;

    /**
     * ล้าง OAuth2 access token ออกจาก cache
     */
    public function clearToken(string $scope = ''): void;

    /**
     * ล้าง JWT access token ออกจาก cache
     */
    public function clearTokenJwt(string $scope = ''): void;
}
