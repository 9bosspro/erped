<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use JsonException;

/**
 * DataNormalization — Trait สำหรับการทำ Data Canonicalization และ Normalization
 *
 * ช่วยให้การทำ Hashing หรือ Signing เป็นแบบ Deterministic (ผลลัพธ์คงที่แม้ลำดับ Key ใน Array ต่างกัน)
 */
trait DataNormalization
{
    /**
     * Normalize mixed data to string for Hashing/Encryption
     */
    protected function normalizeData(mixed $data): string
    {
        if (\is_string($data)) {
            return $data;
        }

        if (\is_scalar($data)) {
            return (string) $data;
        }

        if ($data === null) {
            return '';
        }

        // At this point, $data must be an array or object
        /** @var array<mixed>|object $data */
        return $this->canonicalize($data);
    }

    /**
     * Canonicalize data — sort keys recursively and serialize as JSON
     *
     * @param  array<mixed>|object  $data
     */
    protected function canonicalize(mixed $data): string
    {
        if (\is_object($data)) {
            $data = (array) $data;
        }

        if (\is_array($data)) {
            $data = $this->sortKeysRecursive($data);
        }

        return \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sort array keys recursively for deterministic serialization
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function sortKeysRecursive(array $data): array
    {
        \ksort($data);

        foreach ($data as &$value) {
            if (\is_array($value)) {
                $value = $this->sortKeysRecursive($value);
            } elseif (\is_object($value)) {
                $value = $this->sortKeysRecursive((array) $value);
            }
        }
        unset($value);

        return $data;
    }

    /**
     * Deserialize decrypted plaintext กลับเป็นค่าดั้งเดิม
     *
     * ถ้า plaintext เป็น JSON object/array → คืนเป็น array
     * มิฉะนั้นคืนเป็น string ตามเดิม
     */
    protected function deserializeData(string $plaintext): mixed
    {
        try {
            $decoded = json_decode($plaintext, true, 100, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            \sodium_memzero($plaintext);

            return $plaintext;
        }

        if (\is_array($decoded)) {
            \sodium_memzero($plaintext);

            return $decoded;
        }

        // sodium_memzero($plaintext);
        return $plaintext;
    }
}
