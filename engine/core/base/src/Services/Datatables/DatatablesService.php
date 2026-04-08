<?php

declare(strict_types=1);

namespace Core\Base\Services\Datatables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DatatablesService — สร้าง DataTables-compatible JSON response
 *
 * รองรับ: search, column ordering, pagination
 * ใช้กับ jQuery DataTables / compatible frontend
 *
 * Usage:
 *   $service = new DatatablesService();
 *   return $service->fromQuery($query, $options, $request);
 */
class DatatablesService
{
    /**
     * สร้าง DataTables JSON response จาก query builder
     *
     * @param  EloquentBuilder|QueryBuilder  $query  Query ที่ต้องการแสดงผล
     * @param  array{
     *     column_search?: string[],
     *     column_order?: string[],
     *     order?: array<string, string>,
     * }  $options  ตั้งค่า columns สำหรับ search/order
     * @param  Request  $request  HTTP request ที่มี DataTables parameters
     */
    public function fromQuery(
        EloquentBuilder|QueryBuilder $query,
        array $options,
        Request $request,
    ): JsonResponse {
        $draw = (int) ($request->input('draw', 0));
        $start = max(0, (int) ($request->input('start', 0)));
        $length = (int) ($request->input('length', 0));
        $searchValue = (string) ($request->input('search.value', ''));
        $orderParams = $request->input('order', []);

        // นับ total ก่อน filter
        $totalAll = (clone $query)->count();

        // ── Search ──────────────────────────────────────────────────
        $columnSearch = $options['column_search'] ?? [];

        if ($searchValue !== '' && $columnSearch !== []) {
            $query->where(function (EloquentBuilder|QueryBuilder $q) use ($columnSearch, $searchValue): void {
                $escapedValue = $this->escapeLike($searchValue);

                foreach ($columnSearch as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'like', "%{$escapedValue}%");
                }
            });
        }

        // นับ total หลัง filter
        $totalFiltered = (clone $query)->count();

        // ── Order ───────────────────────────────────────────────────
        $this->applyOrder($query, $orderParams, $options);

        // ── Pagination ──────────────────────────────────────────────
        if ($length > 0) {
            $query->offset($start)->limit($length);
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalAll,
            'recordsFiltered' => $totalFiltered,
            'data' => $query->get(),
        ], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * Group array data ตาม key
     *
     * @param  string  $key  ชื่อ key ที่ต้องการ group
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupBy(string $key, array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            $groupKey = $item[$key] ?? '';
            $result[$groupKey][] = $item;
        }

        return $result;
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * Escape special characters ใน LIKE clause เพื่อป้องกัน SQL injection
     */
    private function escapeLike(string $value): string
    {
        return str_replace(
            ['%', '_', '\\'],
            ['\\%', '\\_', '\\\\'],
            $value,
        );
    }

    /**
     * Apply ordering จาก DataTables request parameters
     */
    private function applyOrder(
        EloquentBuilder|QueryBuilder $query,
        array $orderParams,
        array $options,
    ): void {
        $columnOrder = $options['column_order'] ?? [];

        if (! empty($orderParams)) {
            $colIndex = (int) ($orderParams[0]['column'] ?? 0);
            $dir = strtolower($orderParams[0]['dir'] ?? 'asc');
            $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

            if (isset($columnOrder[$colIndex])) {
                $query->orderBy($columnOrder[$colIndex], $dir);
            }
        } elseif (! empty($options['order'])) {
            foreach ($options['order'] as $column => $direction) {
                $dir = strtolower($direction);
                $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
                $query->orderBy($column, $dir);
            }
        }
    }
}
