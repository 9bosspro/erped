<?php

declare(strict_types=1);

namespace Core\Base\Services\PhpSpreadsheet;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

/**
 * PhpSpreadsheetService — อ่าน/นำเข้าไฟล์ spreadsheet (CSV, XLSX, XLS)
 *
 * หน้าที่:
 *  - Parse ไฟล์เป็น array (เลือก columns ได้)
 *  - แปลง raw data เป็น associative array ตาม headers
 *  - แปลง collection (จาก DB) เป็น indexed array สำหรับ export
 *  - Import ไฟล์เข้า DB ด้วย upsert (chunk 1024 records)
 */
class PhpSpreadsheetService
{
    private const SUPPORTED_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    /**
     * อ่านไฟล์ spreadsheet แล้วคืน array ของ rows
     *
     * @param  string  $filepath  path ของไฟล์
     * @param  string[]  $selectFields  columns ที่ต้องการ (ว่าง = ทั้งหมด)
     * @param  bool  $skipHeader  true = ตัด row แรก (header) ออกจาก result
     * @return array<int, array<int, mixed>>
     *
     * @throws RuntimeException ถ้าไฟล์ไม่พบหรือ extension ไม่รองรับ
     */
    public function parseFile(string $filepath, array $selectFields = [], bool $skipHeader = false): array
    {
        if ($filepath === '') {
            throw new RuntimeException('Filepath must not be empty.');
        }

        if (! file_exists($filepath)) {
            throw new RuntimeException("File not found: {$filepath}");
        }

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new RuntimeException("Unsupported file extension: {$extension}");
        }

        $reader = $this->createReader($extension);
        $spreadsheet = $reader->load($filepath);
        $allData = $spreadsheet->getActiveSheet()->toArray();

        if (empty($allData)) {
            return [];
        }

        $headers = $allData[0];

        if ($selectFields === []) {
            $selectFields = $headers;
        }

        $selectFields = gen_subset_arrays($selectFields, $headers);

        // Filter columns ถ้า selectFields ไม่ตรงกับ headers ทั้งหมด
        $data = collect($allData);

        if ($selectFields !== $headers) {
            $data = $data->map(function (array $row) use ($headers, $selectFields): array {
                foreach ($headers as $index => $columnName) {
                    if (! in_array($columnName, $selectFields, true)) {
                        unset($row[$index]);
                    }
                }

                return array_values($row);
            });
        }

        $result = $data->all();

        if ($skipHeader) {
            unset($result[0]);
            $result = array_values($result);
        }

        return $result;
    }

    /**
     * แปลง indexed array (จาก parseFile) เป็น associative array ตาม headers
     *
     * Row แรกถือเป็น header → ตัดออกจาก result
     *
     * @param  array<int, array<int, mixed>>  $data  ข้อมูลที่ row[0] เป็น headers
     * @param  string[]  $selectFields  columns ที่ต้องการ (ว่าง = ทั้งหมด)
     * @return Collection<int, array<string, mixed>>
     */
    public function toAssociative(array $data, array $selectFields = []): Collection
    {
        if (empty($data)) {
            /** @var Collection<int, array<string, mixed>> $emptyCollection */
            $emptyCollection = collect();

            return $emptyCollection;
        }

        if (is_associative_array($data)) {
            /** @var Collection<int, array<string, mixed>> $emptyCollection */
            $emptyCollection = collect();

            return $emptyCollection;
        }

        $headers = (array) $data[0];
        unset($data[0]);

        if ($selectFields === []) {
            $selectFields = array_map(fn ($h) => (string) $h, $headers);
        }

        $items = array_values($data);

        /** @var Collection<int, array<string, mixed>> $result */
        $result = collect($items)->map(function (array $row) use ($headers, $selectFields): array {
            $mapped = [];

            foreach ($headers as $index => $columnName) {
                $colStr = (string) $columnName;
                if (in_array($colStr, $selectFields, true) && isset($row[$index])) {
                    $mapped[$colStr] = $row[$index];
                }
            }

            return $mapped;
        });

        return $result;
    }

    /**
     * แปลง collection (จาก DB) เป็น indexed array สำหรับ export
     *
     * Row แรกจะเป็น headers (union ของ keys ทุก row)
     *
     * @param  Collection<int, array<string, mixed>>  $collection  ข้อมูลจาก DB query
     * @return Collection<int, array<int, mixed>>
     */
    public function fromCollection(Collection $collection): Collection
    {
        if ($collection->isEmpty()) {
            /** @var Collection<int, array<int, mixed>> $emptyCollection */
            $emptyCollection = collect();

            return $emptyCollection;
        }

        // สร้าง headers จาก union ของทุก keys
        /** @var string[] $headers */
        $headers = $collection->reduce(
            fn (array $carry, array $item) => gen_union_arrays($carry, array_keys($item)),
            [],
        );

        // แปลง associative → indexed ตามลำดับ headers
        /** @var Collection<int, array<int, mixed>> $data */
        $data = $collection->map(function (array $item) use ($headers): array {
            $row = [];
            foreach ($headers as $columnName) {
                $row[] = $item[$columnName] ?? null;
            }

            return $row;
        });

        // Prepend headers เป็น row แรก
        return $data->prepend($headers)->values();
    }

    /**
     * Import ไฟล์ spreadsheet เข้า DB ด้วย upsert (chunk 1024 records)
     *
     * @param  string  $file  path ของไฟล์ (relative to storage disk)
     * @param  mixed  $model  Eloquent model ที่มี method list_Columns()
     * @param  string[]  $selectFields  columns ที่ต้องการ import (ว่าง = ทั้งหมด)
     * @param  string[]  $updateColumns  columns ที่ต้องการ update เมื่อ conflict
     * @param  string[]  $uniqueKeys  columns ที่เป็น unique key สำหรับ upsert
     * @return array<int, array<int, array<string, mixed>>> ข้อมูลที่ import สำเร็จ
     */
    public function importToDatabase(
        string $file,
        mixed $model,
        array $selectFields = [],
        array $updateColumns = [],
        array $uniqueKeys = [],
    ): array {
        if ($file === '') {
            return [];
        }

        $filepath = Storage::path($file);
        $data = $this->parseFile($filepath);

        if (empty($data)) {
            return [];
        }

        $headers = (array) $data[0];
        $dbColumns = (array) $model->list_Columns();
        $headers = gen_subset_arrays($headers, $dbColumns);

        if (empty($headers)) {
            return [];
        }

        // Resolve unique keys
        $uniqueKeys = gen_subset_arrays($uniqueKeys, $headers);

        if (empty($uniqueKeys)) {
            if (empty($headers[0])) {
                return [];
            }
            $uniqueKeys = [(string) $headers[0]];
        }

        // Resolve select fields
        if ($selectFields === []) {
            $selectFields = array_map(fn ($h) => (string) $h, $headers);
        }
        $selectFields = gen_subset_arrays($selectFields, $headers);

        // แปลงเป็น associative
        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = $this->toAssociative($data, $selectFields);

        if ($collection->isEmpty()) {
            return [];
        }

        // Resolve update columns
        $updateColumns = gen_subset_arrays($updateColumns, $selectFields);
        $updateColumns = gen_diff_arrays($updateColumns, $uniqueKeys);

        if (empty($updateColumns)) {
            $updateColumns = gen_diff_arrays($selectFields, $uniqueKeys);
        }

        // Filter rows ที่ unique key ไม่ว่าง
        $filtered = $collection->filter(function (array $item) use ($uniqueKeys): bool {
            foreach ($uniqueKeys as $key) {
                if (empty($item[$key])) {
                    return false;
                }
            }

            return true;
        });

        // Upsert เป็น chunks
        $results = [];

        foreach ($filtered->chunk(1024) as $chunk) {
            $chunkData = $chunk->values()->all();
            $results[] = $chunkData;
            $model::upsert($chunkData, $uniqueKeys, $updateColumns);
        }

        return $results;
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * สร้าง reader ตาม file extension
     */
    private function createReader(string $extension): IReader
    {
        return match ($extension) {
            'csv' => $this->createCsvReader(),
            'xlsx' => (new Xlsx)->setReadDataOnly(true),
            'xls' => (new Xls)->setReadDataOnly(true),
            default => throw new RuntimeException("Unsupported file extension in reader factory: {$extension}"),
        };
    }

    private function createCsvReader(): Csv
    {
        $reader = new Csv;
        $reader->setDelimiter(',');
        $reader->setEnclosure('');
        $reader->setSheetIndex(0);

        return $reader;
    }
}
