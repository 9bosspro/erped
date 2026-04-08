<?php

declare(strict_types=1);

namespace Core\Base\Services\PhpSpreadsheet;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * ChunkReadFilter — กรองแถวที่ต้องการอ่านจาก PhpSpreadsheet
 *
 * ใช้สำหรับอ่านไฟล์ Excel ขนาดใหญ่ทีละ chunk เพื่อประหยัด memory
 *
 * การใช้งาน:
 * ```php
 * $filter = new ChunkReadFilter();
 * $filter->setRows(2, 100); // อ่านแถว 2-101 (+ header แถว 1 อัตโนมัติ)
 * $reader->setReadFilter($filter);
 * ```
 */
final class ChunkReadFilter implements IReadFilter
{
    /** @var int แถวเริ่มต้น (inclusive) */
    private int $startRow = 0;

    /** @var int แถวสิ้นสุด (exclusive) */
    private int $endRow = 0;

    /**
     * กำหนดช่วงแถวที่ต้องการอ่าน
     *
     * @param  int  $startRow  แถวเริ่มต้น (ไม่นับ header)
     * @param  int  $chunkSize  จำนวนแถวที่ต้องการอ่าน
     */
    public function setRows(int $startRow, int $chunkSize): void
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    /**
     * ตรวจสอบว่า cell อยู่ในช่วงที่ต้องการอ่านหรือไม่
     *
     * อ่าน header (แถว 1) เสมอ + แถวในช่วงที่กำหนด
     *
     * @param  mixed  $columnAddress  ที่อยู่ column (string)
     * @param  mixed  $row  หมายเลขแถว (int)
     * @param  mixed  $worksheetName  ชื่อ worksheet (string)
     */
    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row === 1 || ($row >= $this->startRow && $row < $this->endRow);
    }
}
