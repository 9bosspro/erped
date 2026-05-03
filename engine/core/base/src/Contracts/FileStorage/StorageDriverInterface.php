<?php

declare(strict_types=1);

namespace Core\Base\Contracts\FileStorage;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;

/**
 * StorageDriverInterface — contract สำหรับ storage driver ทุกตัว
 *
 * ทุก adapter (MinioAdapter, LocalStorageAdapter, GoogleDriveAdapter)
 * ต้อง implement interface นี้เพื่อให้ระบบสามารถสลับ driver ได้โดยไม่ต้องแก้ code
 *
 * ออกแบบตาม Liskov Substitution Principle —
 * driver ใดก็ตามที่ implement interface นี้ใช้แทนกันได้ทันที
 */
interface StorageDriverInterface
{
    /**
     * @param  string  $disk  ชื่อ disk ใน config/filesystems.php
     */
    public function __construct(string $disk);

    // =========================================================================
    // Driver Information
    // =========================================================================

    /**
     * คืนชื่อ disk ที่ driver นี้จัดการ (ตรงกับ key ใน config/filesystems.php)
     *
     * ตัวอย่าง: 'minio', 'local', 's3-backup'
     */
    public function getDisk(): string;

    /**
     * คืนประเภทของ driver (ตรงกับ config "driver" ใน filesystems.php)
     *
     * ตัวอย่าง: 's3', 'local', 'google'
     */
    public function getDriver(): string;

    // =========================================================================
    // Upload Operations
    // =========================================================================

    /**
     * อัพโหลดไฟล์ไปยัง directory ที่กำหนด (ชื่อไฟล์สุ่มอัตโนมัติ)
     *
     * @param  UploadedFile  $file  ไฟล์ที่ต้องการ upload
     * @param  string  $directory  directory ปลายทาง
     * @param  array<string, mixed>  $metadata  metadata เพิ่มเติม
     * @return array<string, mixed> ข้อมูลไฟล์หลัง upload (path, mime, size, url, ...)
     */
    public function store(UploadedFile $file, string $directory = 'files', array $metadata = []): array;

    /**
     * อัพโหลดไฟล์ไปยัง directory ที่กำหนด พร้อมระบุชื่อไฟล์เอง
     *
     * @param  UploadedFile  $file  ไฟล์ที่ต้องการ upload
     * @param  string  $directory  directory ปลายทาง
     * @param  string|null  $name  ชื่อไฟล์ปลายทาง (null = สุ่มอัตโนมัติ)
     * @param  array<string, mixed>  $metadata  metadata เพิ่มเติม
     * @return array<string, mixed>|null ข้อมูลไฟล์หลัง upload | null ถ้าล้มเหลว
     */
    public function storeAs(UploadedFile $file, string $directory, ?string $name = null, array $metadata = []): ?array;

    // =========================================================================
    // Basic File Operations
    // =========================================================================

    /**
     * ตรวจสอบว่าไฟล์มีอยู่บน storage หรือไม่
     */
    public function exists(string $path): bool;

    /**
     * เขียน raw content ลง storage
     *
     * @param  array<string, mixed>  $options  options เพิ่มเติม (visibility, ...)
     */
    public function put(string $path, string $content, array $options = []): bool;

    /**
     * อ่านเนื้อหาของไฟล์ คืน null ถ้าไม่พบ
     */
    public function read(string $path): ?string;

    /**
     * ลบไฟล์ออกจาก storage คืน false ถ้าไม่พบไฟล์
     */
    public function delete(string $path): bool;

    /**
     * ลบหลายไฟล์พร้อมกัน
     *
     * @param  string[]  $paths  รายการ path ที่ต้องการลบ
     */
    public function deleteMany(array $paths): bool;

    // =========================================================================
    // URL Generation & File Info
    // =========================================================================

    /**
     * คืน public URL ของไฟล์
     */
    public function url(string $path): string;

    /**
     * สร้าง temporary URL ที่หมดอายุตามเวลาที่กำหนด (pre-signed URL)
     *
     * @param  string  $path  path ของไฟล์
     * @param  DateTimeInterface  $expiration  เวลาหมดอายุ
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiration): string;

    /**
     * คืนขนาดไฟล์ (bytes)
     */
    public function size(string $path): int;

    /**
     * คืน MIME type ของไฟล์ คืน null ถ้าไม่สามารถระบุได้
     */
    public function mimeType(string $path): ?string;

    /**
     * คืน metadata ของไฟล์ (path, disk, size, mime, url, last_modified)
     *
     * @return array<string, mixed>
     */
    public function metadata(string $path): array;

    // =========================================================================
    // File Management
    // =========================================================================

    /**
     * คัดลอกไฟล์จาก path ต้นทางไปยัง path ปลายทาง
     */
    public function copy(string $from, string $to): bool;

    /**
     * ย้ายไฟล์จาก path ต้นทางไปยัง path ปลายทาง
     */
    public function move(string $from, string $to): bool;

    // =========================================================================
    // Directory Operations
    // =========================================================================

    /**
     * รายการไฟล์ใน directory
     *
     * @param  bool  $recursive  true = รวม subdirectory ทั้งหมด
     * @return string[]
     */
    public function files(string $directory = '', bool $recursive = false): array;

    /**
     * รายการ directory ย่อยใน directory
     *
     * @param  bool  $recursive  true = รวม subdirectory ทั้งหมด
     * @return string[]
     */
    public function directories(string $directory = '', bool $recursive = false): array;

    /**
     * สร้าง directory ใหม่
     */
    public function makeDirectory(string $path): bool;

    /**
     * ลบ directory พร้อมเนื้อหาทั้งหมด
     */
    public function deleteDirectory(string $path): bool;

    // =========================================================================
    // HTTP Response
    // =========================================================================

    /**
     * คืน download response สำหรับ browser (Content-Disposition: attachment)
     *
     * @param  string|null  $name  ชื่อไฟล์ที่ browser จะเห็น
     * @param  array<string, mixed>  $headers  HTTP headers เพิ่มเติม
     * @return \Symfony\Component\HttpFoundation\Response StreamedResponse หรือ BinaryFileResponse
     */
    public function download(string $path, ?string $name = null, array $headers = []): \Symfony\Component\HttpFoundation\Response;

    /**
     * คืน inline response สำหรับ browser (Content-Disposition: inline)
     *
     * @param  string|null  $name  ชื่อไฟล์ที่ browser จะเห็น
     * @param  array<string, mixed>  $headers  HTTP headers เพิ่มเติม
     * @return mixed StreamedResponse หรือ BinaryFileResponse
     */
    public function response(string $path, ?string $name = null, array $headers = []): mixed;
}
