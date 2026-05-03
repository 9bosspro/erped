<?php

declare(strict_types=1);

namespace Core\Base\Services\Log;

use Core\Base\Services\Log\Contracts\AuditServiceInterface;
use Core\Base\Support\Helpers\Logs\Contracts\AppLoggerInterface;
use Illuminate\Support\Str;

/**
 * AuditService — ระบบบันทึกประวัติการใช้งานระดับ Enterprise
 *
 * ทำหน้าที่เป็นหัวใจหลักในการเก็บประวัติ (Audit Trail) โดยเน้น:
 * 1. Metadata Enrichment ( Trace ID, Module Context)
 * 2. Data Redaction (Masking sensitive info)
 * 3. Standardization (โครงสร้างข้อมูลสม่ำเสมอ)
 */
class AuditService implements AuditServiceInterface
{
    /** @var string[] รายการ keys ที่ต้องถูก Mask ออกจาก metadata เสมอ */
    protected array $maskedKeys = [
        'password', 'token', 'secret', 'key', 'card', 'cvv', 'pin', 'ssn',
    ];

    public function __construct(
        protected AppLoggerInterface $logger,
    ) {
        // ดึงการตั้งค่าคีย์ที่ต้องการปิดบังเพิ่มเติมได้จากไฟล์ Config (ความยืดหยุ่นสูง)
        /** @var array<string> $extraKeys */
        $extraKeys = array_filter((array) config('logging.audit.masked_keys', []), '\is_string');
        if (count($extraKeys) > 0) {
            $this->maskedKeys = array_values(array_unique(array_merge($this->maskedKeys, $extraKeys)));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function log(string $event, string $subject = '', array $data = [], ?string $module = null): void
    {
        $context = $this->buildAuditContext($event, $subject, $data, $module);

        $this->logger->audit($event, $subject, $data, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function logSecurity(string $event, array $data = []): void
    {
        $this->logger->security($event, $this->redact($data));
    }

    /**
     * สร้าง Context มาตรฐานสำหรับ Audit Log
     */
    protected function buildAuditContext(string $event, string $subject, array $data, ?string $module): array
    {
        return [
            'event_type' => $event,
            'subject' => $subject,
            'module_source' => $module ?? $this->detectModule(),
            'event_timestamp' => now()->toIso8601String(),
            'payload' => $this->redact($data),
        ];
    }

    /**
     * ค้นหา Module ต้นทางจาก Stack Trace (ถ้าไม่ได้ระบุมา)
     */
    protected function detectModule(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($trace as $frame) {
            if (isset($frame['file']) && str_contains($frame['file'], 'engine/modules/')) {
                $path = $frame['file'];
                $parts = explode('engine/modules/', $path);
                if (isset($parts[1])) {
                    return explode('/', $parts[1])[0];
                }
            }
        }

        return 'core';
    }

    /**
     * ล้างข้อมูล Sensitive ออกจาก Metadata
     */
    protected function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (\is_string($key)) {
                foreach ($this->maskedKeys as $pattern) {
                    if (Str::contains(strtolower($key), $pattern)) {
                        $data[$key] = '***';
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
