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
     * Normalize mixed data ให้เป็น string สำหรับ Hashing
     */
    protected function normalizeData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_numeric($data)) {
            return (string) $data;
        }

        return $this->canonicalize($data);
    }

    /**
     * Canonicalize data — sort keys recursively และ serialize เป็น JSON
     */
    protected function canonicalize(mixed $data): string
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            $data = $this->sortKeysRecursive($data);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Sort array keys recursively สำหรับ deterministic serialization
     */
    protected function sortKeysRecursive(array $data): array
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->sortKeysRecursive($value);
            } elseif (is_object($value)) {
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
            $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $plaintext;
        }

        if (\is_array($decoded)) {
            return $decoded;
        }

        return $plaintext;
    }
}
