<?php

declare(strict_types=1);

namespace Core\Base\Services\Imgproxy\Contracts;

use Core\Base\Services\Imgproxy\ImgproxyUrlBuilder;

interface ImgproxyServiceInterface
{
    /**
     * สร้าง URL builder ใหม่สำหรับ source URL ที่กำหนด
     *
     * คืน builder instance ใหม่ทุกครั้ง — thread-safe, ไม่มี shared state
     */
    public function url(string $sourceUrl): ImgproxyUrlBuilder;

    /**
     * Sign path ด้วย HMAC-SHA256 (key + salt จาก config)
     *
     * @return string URL-safe base64 signature หรือ 'unsafe' ถ้าไม่มี key/salt
     */
    public function sign(string $path): string;

    /**
     * ตรวจว่า signing ถูกเปิดใช้งาน (มีทั้ง key และ salt)
     */
    public function isSigningEnabled(): bool;

    /**
     * คืน imgproxy endpoint URL
     */
    public function getEndpoint(): string;
}
