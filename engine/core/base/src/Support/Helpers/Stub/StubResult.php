<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Stub;

/**
 * StubResult — Value Object แสดงผลการ generate stub ไฟล์
 *
 * - written  = เขียนไฟล์สำเร็จ
 * - skipped  = ข้ามเพราะไฟล์มีอยู่แล้วและ overwrite = false
 * - dryRun   = ไม่ได้เขียนจริง เพราะ dry-run mode
 */
final readonly class StubResult
{
    public function __construct(
        /** Absolute path ของไฟล์ปลายทาง */
        public string $path,

        /** เนื้อหาที่จะ/ได้เขียน */
        public string $content,

        /** true = เขียนไฟล์จริงสำเร็จ */
        public bool $written,

        /** true = ข้ามการเขียนเพราะไฟล์มีอยู่แล้ว */
        public bool $skipped,

        /** true = อยู่ใน dry-run mode (ไม่เขียนจริง) */
        public bool $dryRun,
    ) {}

    /**
     * คืน status สรุปเป็น string สำหรับ output ใน Console
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->dryRun => '[DRY-RUN]',
            $this->skipped => '[SKIPPED]',
            $this->written => '[CREATED]',
            default => '[UNKNOWN]',
        };
    }

    /**
     * ชื่อไฟล์สั้น (basename เฉยๆ)
     */
    public function filename(): string
    {
        return basename($this->path);
    }

    /**
     * แปลงเป็น array (เพื่อ logging / JSON response)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'status' => $this->statusLabel(),
            'written' => $this->written,
            'skipped' => $this->skipped,
            'dryRun' => $this->dryRun,
        ];
    }
}
