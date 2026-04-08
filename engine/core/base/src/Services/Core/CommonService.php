<?php

declare(strict_types=1);

namespace Core\Base\Services\Core;

use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class CommonService
{
    //
    public function __construct()
    {
        //
    }

    public function base64UrlEncode(string $data, bool $padding = true): string
    {
        // ⚡️ ใช้ Sodium Optimized Version (จาก AppHelper)
        return $padding
            ? \sodium_bin2base64($data, SODIUM_BASE64_VARIANT_URLSAFE)
            : encodeb64UrlSafe($data);
    }

    public function base64UrlDecode(string $base64Url): string|false
    {
        try {
            // ⚡️ ใช้ Sodium Optimized Version
            // sodium_base642bin รองรับทั้งแบบมีและไม่มี padding ใน variant เดียวกัน
            return \sodium_base642bin($base64Url, SODIUM_BASE64_VARIANT_URLSAFE);
        } catch (Throwable) {
            return false;
        }
    }

    public function data_to_url(string $data, bool $removePadding = true): string
    {
        return $this->base64UrlEncode($data, ! $removePadding);
    }

    public function url_to_data(string $data, bool $addPadding = false): string|false
    {
        return $this->base64UrlDecode($data);
    }

    public function is_valid_base64(mixed $data, bool $strict = true): bool
    {
        if (! is_string($data) || $data === '') {
            return false;
        }

        if ($strict) {
            // ตรวจสอบ pattern และ padding
            if (! preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $data)) {
                return false;
            }
            if (strlen($data) % 4 !== 0) {
                return false;
            }
        }

        $decoded = base64_decode($data, true);

        return $decoded !== false && base64_encode($decoded) === $data;
    }

    public function generateId(int $version = 4, bool $includeDash = false): string
    {
        try {
            $uuid = match ($version) {
                1 => Uuid::uuid1(),
                2 => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON),
                3 => Uuid::uuid3(Uuid::NAMESPACE_DNS, php_uname('n')),
                5 => Uuid::uuid5(Uuid::NAMESPACE_DNS, php_uname('n')),
                6 => Uuid::uuid6(),
                7 => Uuid::uuid7(),
                default => Uuid::uuid7(),
            };
        } catch (Throwable $e) {
            throw new RuntimeException("UUID v{$version} generation failed: {$e->getMessage()}", previous: $e);
        }

        $string = $uuid->toString();

        return $includeDash ? $string : str_replace('-', '', $string);
    }

    /**
     * สร้าง UUID version 5 (deterministic — input เดิม = UUID เดิมเสมอ)
     *
     * @param  string  $name  input string (ใช้ hostname ถ้าว่าง)
     * @param  bool  $includeDash  true = มี dash เช่น 550e8400-e29b-...
     */
    public function uuid5(string $name = '', bool $includeDash = false): string
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, $name ?: php_uname('n'))->toString();

        return $includeDash ? $uuid : str_replace('-', '', $uuid);
    }
}
