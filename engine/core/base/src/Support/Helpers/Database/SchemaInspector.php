<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SchemaInspector — ตรวจสอบโครงสร้างฐานข้อมูล
 *
 * ความรับผิดชอบ:
 * - แสดงรายชื่อ databases ทั้งหมด
 * - แสดง columns และ types ของ table
 *
 * หมายเหตุ: ใช้สำหรับ dev tooling / admin inspection เท่านั้น
 *            ไม่ควรเรียกใน hot path ของ production
 */
final class SchemaInspector
{
    /**
     * คืนรายชื่อ databases ทั้งหมด (ยกเว้น information_schema)
     *
     * @return array<string>
     */
    public static function listDatabases(): array
    {
        $systemDbs = ['information_schema', 'performance_schema', 'mysql', 'sys'];

        return collect(DB::select('SHOW DATABASES'))
            ->pluck('Database')
            ->reject(fn ($db) => in_array($db, $systemDbs))
            ->values()
            ->toArray();
    }

    /**
     * คืนรายการ columns พร้อม type ของ table
     *
     * @param  string  $table  ชื่อ table
     * @param  string  $connection  ชื่อ connection (default = 'mysql')
     * @return array<array{field: string, type: string}>
     */
    public static function listColumnsWithTypes(string $table, string $connection = 'mysql'): array
    {
        $schema = Schema::connection($connection);
        $columns = $schema->getColumnListing($table);

        return array_map(fn($column) => [
            'field' => $column,
            'type' => $schema->getColumnType($table, $column),
        ], $columns);
    }

    /**
     * คืนรายชื่อ columns ของ table
     *
     * @param  string  $table  ชื่อ table
     * @param  string  $connection  ชื่อ connection (default = 'mysql')
     * @return array<string>
     */
    public static function listColumns(string $table, string $connection = 'mysql'): array
    {
        return Schema::connection($connection)->getColumnListing($table);
    }
}
