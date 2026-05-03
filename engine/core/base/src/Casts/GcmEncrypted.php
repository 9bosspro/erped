<?php

declare(strict_types=1);

namespace Core\Base\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * @implements CastsAttributes<mixed, ?string>
 */
class GcmEncrypted implements CastsAttributes
{
    /**
     * @param  bool  $jsonDecode  เปิดเฉพาะ field ที่รู้ว่าเก็บ JSON เท่านั้น
     */
    public function __construct(
        protected bool $jsonDecode = false,
    ) {}

    /**
     * Decrypt value from storage.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("Expected string value for field [{$key}]");
        }

        try {
            $decrypted = coreDecrypt($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Decryption failed for field [{$key}]",
                previous: $e,
            );
        }

        // ✅ Opt-in เท่านั้น ไม่ decode โดยอัตโนมัติ
        if ($this->jsonDecode) {
            if ($decrypted === null) {
                return null;
            }

            return $this->decodeJson($decrypted, $key);
        }

        return $decrypted;
    }

    /**
     * Encrypt value before storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // $normalized = $this->normalizeValue($value, $key);
        $normalized = normalizeData($value);

        if ($normalized === '') {
            return null;
        }

        try {
            $encrypted = coreEncrypt($normalized);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Encryption failed for field [{$key}]",
                previous: $e,
            );
        }

        if (! is_string($encrypted) || $encrypted === '') {
            throw new InvalidArgumentException(
                "Invalid encrypted output for field [{$key}]",
            );
        }

        return $encrypted;
    }

    /**
     * Normalize input to string.
     */
    protected function normalizeValue(mixed $value, string $key): string
    {
        try {
            return match (true) {
                // ✅ trim และจัดการ empty string
                is_string($value) => trim($value),

                is_int($value),
                is_float($value),
                is_bool($value) => (string) $value,

                is_array($value),
                is_object($value) => json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),

                default => throw new InvalidArgumentException(
                    'Unsupported type ['.get_debug_type($value)."] for field [{$key}]",
                ),
            };
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                "JSON encode failed for field [{$key}]",
                previous: $e,
            );
        }
    }

    /**
     * Decode JSON — ใช้เฉพาะเมื่อ jsonDecode = true
     */
    protected function decodeJson(string $value, string $key): mixed
    {
        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(
                "JSON decode failed for field [{$key}]: value is not valid JSON",
                previous: $e,
            );
        }
    }
}
