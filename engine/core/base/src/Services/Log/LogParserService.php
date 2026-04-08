<?php

declare(strict_types=1);

namespace Core\Base\Services\Log;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LogParserService — อ่าน Laravel log file แล้วบันทึกลง database
 *
 * คุณสมบัติ:
 *  - Atomic lock ป้องกันทำงานซ้อน (cache lock 600 วินาที)
 *  - ตรวจพื้นที่ดิสก์ก่อนเริ่ม (ค่าเริ่มต้น 500 MB)
 *  - SHA-256 fingerprint ป้องกัน duplicate
 *  - อ่านต่อจาก offset เดิม (incremental parsing)
 *  - Batch upsert แทน row-by-row เพื่อ performance
 *
 * Config keys (logging.parser.*):
 *  - table: ชื่อ table สำหรับเก็บ log (default: 'system_logs')
 *  - batch_size: จำนวน records ต่อ batch (default: 500)
 *  - min_disk_space_mb: พื้นที่ดิสก์ขั้นต่ำเป็น MB (default: 500)
 */
class LogParserService
{
    private string $logFile = 'laravel.log';

    /** ชื่อ table ที่ใช้เก็บ parsed logs */
    private readonly string $tableName;

    /** จำนวน records ต่อ batch upsert */
    private readonly int $batchSize;

    /** พื้นที่ดิสก์ขั้นต่ำ (bytes) */
    private readonly int $minDiskSpaceFree;

    public function __construct()
    {
        $this->tableName = (string) config('logging.parser.table', 'system_logs');
        $this->batchSize = (int) config('logging.parser.batch_size', 500);

        $minDiskMb = (int) config('logging.parser.min_disk_space_mb', 500);
        $this->minDiskSpaceFree = $minDiskMb * 1024 * 1024;
    }

    /**
     * อ่าน log file แล้วบันทึกลง database ด้วย batch upsert
     *
     * กระบวนการ:
     *  1. ตรวจว่าไฟล์มีอยู่
     *  2. ขอ atomic lock ป้องกันทำงานซ้อน
     *  3. ตรวจพื้นที่ดิสก์
     *  4. อ่านต่อจาก offset เดิม (incremental)
     *  5. Batch upsert ลง database
     *
     * @param  string|null  $fileName  ชื่อไฟล์ log (default: laravel.log)
     * @return string ข้อความสรุปผลการทำงาน
     */
    public function parseFileToDatabase(?string $fileName = null): string
    {
        $fileName = $fileName ?: $this->logFile;
        $path = storage_path("logs/{$fileName}");

        if (! File::exists($path)) {
            return "ไม่พบไฟล์ Log: {$fileName}";
        }

        $lock = Cache::lock('processing_log_'.$fileName, 600);

        if (! $lock->get()) {
            return 'สคริปต์กำลังทำงานอยู่ในขณะนี้ (Locked)';
        }

        try {
            // ตรวจพื้นที่ดิสก์
            $freeSpace = Cache::remember('disk_free_space', 60, fn (): float|false => disk_free_space(storage_path()));

            if ($freeSpace !== false && $freeSpace < $this->minDiskSpaceFree) {
                Log::emergency('LogParser stopped: Disk space is too low!');

                return 'หยุดการทำงาน: พื้นที่ดิสก์เหลือต่ำกว่ากำหนด';
            }

            // อ่านต่อจาก offset เดิม
            $cacheKey = 'log_parser_offset_'.$fileName;
            $lastOffset = (int) Cache::get($cacheKey, 0);
            $fileSize = File::size($path);

            if ($lastOffset > $fileSize) {
                $lastOffset = 0;
            }

            $count = $this->processFile($path, $lastOffset, $cacheKey);

            return "ประมวลผลเสร็จสิ้น: นำเข้าข้อมูลใหม่ {$count} รายการ";
        } finally {
            $lock->release();
        }
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * อ่านและประมวลผลไฟล์ log ทีละ line แล้ว batch upsert
     *
     * @param  string  $path  absolute path ของไฟล์ log
     * @param  int  $offset  byte offset ที่จะเริ่มอ่าน
     * @param  string  $cacheKey  cache key สำหรับเก็บ offset
     * @return int จำนวน records ที่ประมวลผล
     */
    private function processFile(string $path, int $offset, string $cacheKey): int
    {
        $file = fopen($path, 'r');

        if ($file === false) {
            Log::error('LogParser: Cannot open file', ['path' => $path]);

            return 0;
        }

        try {
            fseek($file, $offset);
            $batch = [];
            $count = 0;

            while (($line = fgets($file)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                try {
                    $fingerprint = hash('sha256', $line);
                    $data = json_decode($line, true);

                    $batch[] = (is_array($data) && json_last_error() === JSON_ERROR_NONE)
                        ? $this->buildParsedRow($data, $fingerprint)
                        : $this->buildRawRow($line, $fingerprint);

                    $count++;

                    if (count($batch) >= $this->batchSize) {
                        $this->flushBatch($batch);
                        $batch = [];
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to ingest log line: '.$e->getMessage());
                }
            }

            // Flush remaining records
            if ($batch !== []) {
                $this->flushBatch($batch);
            }

            // บันทึก offset สำหรับรอบถัดไป
            Cache::put($cacheKey, ftell($file), now()->addDay());

            return $count;
        } finally {
            fclose($file);
        }
    }

    /**
     * สร้าง row สำหรับ log ที่ parse สำเร็จ (JSON format)
     *
     * @param  array<string, mixed>  $data  parsed JSON data
     * @param  string  $fingerprint  SHA-256 fingerprint
     * @return array<string, mixed> row พร้อม upsert
     */
    private function buildParsedRow(array $data, string $fingerprint): array
    {
        return [
            'fingerprint' => $fingerprint,
            'log_date' => isset($data['datetime'])
                ? Carbon::parse($data['datetime'])->toDateTimeString()
                : now()->toDateTimeString(),
            'level' => $data['level_name'] ?? 'INFO',
            'event_name' => $data['message'] ?? 'SYSTEM_EVENT',
            'context' => json_encode($data['context'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_parsed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * สร้าง row สำหรับ log ที่ parse ไม่ได้ (raw text)
     *
     * @param  string  $line  raw log line
     * @param  string  $fingerprint  SHA-256 fingerprint
     * @return array<string, mixed> row พร้อม upsert
     */
    private function buildRawRow(string $line, string $fingerprint): array
    {
        return [
            'fingerprint' => $fingerprint,
            'log_date' => now()->toDateTimeString(),
            'level' => 'UNPARSED',
            'event_name' => 'PATTERN_MISMATCH',
            'context' => json_encode(['raw_line' => $line], JSON_UNESCAPED_UNICODE),
            'is_parsed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Batch upsert records ลง database
     *
     * ใช้ fingerprint เป็น unique key เพื่อป้องกัน duplicate
     *
     * @param  array<int, array<string, mixed>>  $batch  records ที่จะ upsert
     */
    private function flushBatch(array $batch): void
    {
        DB::table($this->tableName)->upsert(
            $batch,
            ['fingerprint'],
            ['log_date', 'level', 'event_name', 'context', 'is_parsed', 'updated_at'],
        );
    }
}
