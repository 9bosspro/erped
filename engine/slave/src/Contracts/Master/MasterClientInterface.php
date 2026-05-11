<?php

declare(strict_types=1);

namespace Slave\Contracts\Master;

use Illuminate\Http\Client\Response;

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
    // ─── Fluent Builders ────────────────────────────────────────────────────

    /**
     * คืน instance ใหม่ที่ใช้ token flow ที่ระบุ
     *
     * ใช้งาน: $client->withFlow(TokenFlow::Jwt)->get('/endpoint')
     */
    public function withFlow(TokenFlow $flow): static;

    /**
     * คืน instance ใหม่ที่ขอ token ด้วย scope ที่ระบุ
     *
     * ใช้งาน: $client->withScope('read:users write:orders')->get('/endpoint')
     */
    public function withScope(string $scope): static;

    /**
     * คืน instance ใหม่ที่ใช้ credentials ที่ระบุ
     *
     * ใช้งาน: $client->withCredentials($id, $secret)->post('/endpoint', $data)
     */
    public function withCredentials(string $clientId, string $clientSecret): static;

    /**
     * คืน instance ใหม่ที่บังคับใช้ Bearer token ตามที่ระบุโดยตรง (ข้ามการขอ Auto-Token)
     *
     * ใช้งาน: $client->withToken('eyJ...')->get('/endpoint')
     */
    public function withToken(string $token): static;

    /**
     * คืน instance ใหม่ที่ไม่แนบ HTTP headers เพิ่มเติมไปกับทุก request
     *
     * headers ที่ระบุจะ merge ทับค่าเดิม (ถ้า key ซ้ำ จะใช้ค่าใหม่)
     * ใช้งาน: $client->withHeaders(['X-Tenant' => 'acme'])->get('/endpoint')
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static;

    /**
     * คืน instance ใหม่ที่ไม่แนบ access token ใดๆ (Alias of withoutToken)
     */
    public function disableToken(): static;

    /**
     * คืน instance ใหม่ที่จะไม่แนบ access token ไปกับ request (สำหรับ public endpoints)
     *
     * ใช้งาน: $client->withoutToken()->get('/endpoint')
     */
    public function withoutToken(): static;

    /**
     * คืน instance ใหม่ที่เปิดใช้งานการแคชผลลัพธ์ (มีผลเฉพาะ HTTP GET เท่านั้น)
     *
     * @param int $seconds ระยะเวลาในการแคช (วินาที)
     * @param string|null $store ชื่อ Cache Store ที่ต้องการใช้ (เช่น 'redis', 'session') null เพื่อใช้ default
     * ใช้งาน: $client->cache(600, 'session')->get('/endpoint')
     */
    public function cache(int $seconds, ?string $store = null): static;

    /**
     * คืน instance ใหม่ที่สั่งให้ TokenManager ใช้ Cache Store ที่ระบุในการเก็บ Token
     *
     * @param string|null $store ชื่อช่องทางการเก็บข้อมูล token (เช่น 'redis', 'session')
     * ใช้งาน: $client->withTokenStore('redis')->get('/endpoint')
     */
    public function withTokenStore(?string $store): static;

    /**
     * คืน instance ใหม่ที่ต่อท้ายกุญแจแคช (Cache Key) เพื่อแบ่ง Context ป้องกันข้อมูลชนกัน
     * เหมาะสำหรับระบบ Multi-User หรือต้องการแยกความแตกต่างเฉพาะ
     *
     * @param string $suffix ข้อความที่ต้องการนำมาต่อท้าย (เช่น User ID หรือ Session ID)
     * ใช้งาน: $client->withCacheSuffix(auth()->id())->get('/profile')
     */
    public function withCacheSuffix(string $suffix): static;

    // ─── HTTP Methods ────────────────────────────────────────────────────────

    /**
     * ส่ง HTTP request และคืนค่าดิบ Response object โดยตรง
     *
     * เหมาะกับกรณีที่ต้องการดึงไฟล์ดิบ (Download), ตรวจสอบ header หรือ custom status code
     *
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'  $method
     * @param  array<string, mixed>  $options  query strings หรือ request data
     */
    public function sendRequest(string $method, string $endpoint, array $options = []): Response;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $data = []): array;

    // ─── Utility ─────────────────────────────────────────────────────────────

    /** ตรวจสอบว่า Master Server ตอบสนองได้ปกติหรือไม่ */
    public function ping(): bool;

    /** คืนค่า base URL ของ Master Server */
    public function getBaseUrl(): string;

    // ─── Domain Methods ───────────────────────────────────────────────────────

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
     * อัพโหลดไฟล์แบบ multipart/form-data (POST)
     *
     * เหมาะกับไฟล์ทั่วไปที่ต้องส่ง field เพิ่มเติมไปพร้อมกัน
     *
     * @param  \Psr\Http\Message\StreamInterface|resource|string  $contents  เนื้อหาไฟล์
     * @param  array<string, mixed>  $fields  form fields เพิ่มเติม
     * @return array<string, mixed>
     */
    public function upload(
        string $endpoint,
        string $name,
        mixed $contents,
        string $filename = '',
        string $mimeType = '',
        array $fields = [],
    ): array;

    /**
     * อัพโหลดหลายไฟล์พร้อมกันแบบ multipart/form-data (POST)
     *
     * @param  array<int, array{name: string, contents: mixed, filename?: string, mimeType?: string}>  $files
     * @param  array<string, mixed>  $fields  form fields เพิ่มเติม
     * @return array<string, mixed>
     */
    public function uploadMany(
        string $endpoint,
        array $files,
        array $fields = [],
    ): array;

    /**
     * อัพโหลดไฟล์แบบ raw stream body (PUT หรือ POST)
     *
     * เหมาะกับไฟล์ขนาดใหญ่ เพราะไม่ต้องโหลดทั้งไฟล์ลง memory
     *
     * @param  \Psr\Http\Message\StreamInterface|resource|string  $stream
     * @param  'POST'|'PUT'  $method
     * @return array<string, mixed>
     */
    public function uploadStream(
        string $endpoint,
        mixed $stream,
        string $mimeType = 'application/octet-stream',
        string $method = 'PUT',
    ): array;

    // ─── Token Management ─────────────────────────────────────────────────────

    /**
     * คืนค่า access token ตาม flow ที่ระบุ (ดึงจาก cache หรือขอใหม่)
     * หากไม่ระบุ ($flow = null) จะใช้ Flow และ Scope ตามที่ config ไว้ใน Client ณ ขณะนั้น
     *
     * ใช้งาน: $client->getToken() หรือ $client->getToken(TokenFlow::Jwt)
     */
    public function getToken(?TokenFlow $flow = null, ?string $scope = null): string;

    /**
     * ล้าง cached token ตาม flow ที่ระบุ
     * หากไม่ระบุ จะล้างตาม Flow/Scope ปัจจุบันของ Client
     */
    public function clearToken(?TokenFlow $flow = null, ?string $scope = null): void;

    /**
     * 💥 ลบ Token ทั้งหมดของระบบ (ทุก Flow/Scope) สำหรับ Client ปัจจุบันออกทันที
     */
    public function clearAllTokens(): void;

    /**
     * บันทึก Token Data ดิบลง Cache ด้วยตนเอง
     * หากไม่ระบุ Flow/Scope จะใช้ค่าเริ่มต้นจาก Config ปัจจุบัน
     *
     * @param array<string, mixed> $data
     */
    public function storeToken(array $data, ?TokenFlow $flow = null, ?string $scope = null): void;

    /**
     * ดึง Refresh Token (ถ้ามี) จาก cache หรือ storage ปัจจุบัน
     */
    public function getRefreshToken(?TokenFlow $flow = null, ?string $scope = null): ?string;

    /**
     * ตรวจสอบว่า Token ปัจจุบันหมดอายุแล้วหรือยัง (คำนวณรวม Buffer Safety)
     */
    public function isExpired(?TokenFlow $flow = null, ?string $scope = null): bool;

    /**
     * 🧪 [Debug] ดึงข้อมูลเชิงลึกของ Token ทั้งหมดที่แคชไว้ขณะนี้ออกมาดู (สำหรับตรวจสอบค่าดิบ)
     * @return array<string, mixed>
     */
    public function debugCachedTokens(): array;

    /**
     * 🕵️‍♂️ [Debug] ดึงเฉพาะรายชื่อ Cache Keys ทั้งหมดที่ระบบกำลังติดตามอยู่ (จาก Manifest)
     * @return array<int, string>
     */
    public function getCachedManifest(): array;
}
