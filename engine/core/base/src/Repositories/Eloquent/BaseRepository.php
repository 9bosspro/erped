<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Eloquent;

use Core\Base\Repositories\Interfaces\BaseRepositoryInterface;
use Core\Base\Repositories\Traits\HasCacheOperations;
use Core\Base\Repositories\Traits\HasConcurrencyOperations;
use Core\Base\Repositories\Traits\HasCriteriaOperations;
use Core\Base\Repositories\Traits\HasHookOperations;
use Core\Base\Repositories\Traits\HasPaginationOperations;
use Core\Base\Repositories\Traits\HasQueryOperations;
use Core\Base\Repositories\Traits\HasReadOperations;
use Core\Base\Repositories\Traits\HasSoftDeleteOperations;
use Core\Base\Repositories\Traits\HasWriteOperations;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Repository — คลาสหลักของ Repository Pattern
 *
 * ออกแบบตามหลัก:
 * - ISP (Interface Segregation) — แยก interface ย่อยตาม feature
 * - Composition over Inheritance — ใช้ Trait แทน deep hierarchy
 * - Stateless Query — newQuery() สร้าง Builder ใหม่ทุกครั้ง ไม่มี state ค้าง
 *
 * การใช้งาน:
 * ```php
 * class UserRepository extends BaseRepository implements UserInterface
 * {
 *     public function __construct(User $model)
 *     {
 *         parent::__construct($model);
 *     }
 * }
 * ```
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    use HasCacheOperations;
    use HasConcurrencyOperations;
    use HasCriteriaOperations;
    use HasHookOperations;
    use HasPaginationOperations;
    use HasQueryOperations;
    use HasReadOperations;
    use HasSoftDeleteOperations;
    use HasWriteOperations;

    /**
     * Static cache ของ boot methods แต่ละ Repository class
     *
     * เก็บผลลัพธ์ของ class_uses_recursive() + method_exists() ที่ทำครั้งแรก
     * เพื่อไม่ต้องทำ reflection ซ้ำทุกครั้งที่ instantiate Repository
     *
     * key = FQCN ของ Repository class, value = array ของ boot method names
     *
     * @var array<class-string, string[]>
     */
    private static array $traitBootMethods = [];

    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->bootTraits();
    }

    /**
     * คืนชื่อ class ของ Model แบบ FQCN (Fully Qualified Class Name)
     *
     * ใช้สำหรับ logging, debugging, หรือ dynamic dispatch
     *
     * @return string เช่น "App\Models\User"
     */
    public function getModel(): string
    {
        return get_class($this->model);
    }

    /**
     * คืน Model instance สำหรับกรณีที่ต้องเข้าถึง Model โดยตรง
     *
     * ใช้เมื่อต้องการเรียก method เฉพาะของ Model ที่ Repository ไม่ได้ expose
     * เช่น $repo->modelInstance()->getTable()
     */
    public function modelInstance(): Model
    {
        return $this->model;
    }

    /**
     * คืน Query Builder สำหรับ query ขั้นสูงที่ Repository ไม่ได้รองรับ
     *
     * ⚠️ ควรใช้เท่าที่จำเป็น — ถ้าใช้บ่อย ควรเพิ่ม method ใน Repository แทน
     */
    public function getQuery(): Builder
    {
        return $this->newQuery();
    }

    /**
     * Refresh model instance จากฐานข้อมูล (reload attributes + relations)
     *
     * @param  Model  $model  instance ที่ต้องการ refresh
     * @return Model instance ที่ถูก reload แล้ว
     */
    public function refresh(Model $model): Model
    {
        return $model->refresh();
    }

    /**
     * Boot ทุก Trait ที่มี method boot{TraitName}
     *
     * ใช้ convention เดียวกับ Eloquent Model เพื่อให้ Trait
     * สามารถ initialize state ของตัวเองได้ เช่น default cache tag
     *
     * ตัวอย่าง: HasCacheOperations → bootHasCacheOperations()
     *
     * ผลลัพธ์ของ reflection ถูก cache ใน static $traitBootMethods
     * เพื่อไม่ต้องทำซ้ำทุก instantiation (สำคัญมากเมื่อ bind แบบ non-singleton)
     */
    protected function bootTraits(): void
    {
        $class = static::class;

        if (! isset(self::$traitBootMethods[$class])) {
            $methods = [];

            foreach (class_uses_recursive($class) as $trait) {
                $method = 'boot'.class_basename($trait);
                if (method_exists($this, $method)) {
                    $methods[] = $method;
                }
            }

            self::$traitBootMethods[$class] = $methods;
        }

        foreach (self::$traitBootMethods[$class] as $method) {
            $this->{$method}();
        }
    }

    /**
     * สร้าง Eloquent Query Builder ใหม่
     *
     * - ใช้ newQuery() จาก Model เพื่อได้ Builder ที่สะอาด ไม่มี scope ค้าง
     * - ถ้ามี Criteria ที่สะสมไว้ จะ apply ลงใน query แล้ว auto-reset
     *   เพื่อป้องกัน state leaking ข้ามการเรียกใช้งาน (stateless design)
     * - ใช้ property_exists guard เพื่อ decouple จาก HasCriteriaOperations
     *   ทำให้ BaseRepository ทำงานได้แม้ไม่ได้ use trait นั้น
     */
    protected function newQuery(): Builder
    {
        $query = $this->model->newQuery();

        // Apply criteria แล้ว reset — decouple จาก trait ด้วย property_exists
        /** @phpstan-ignore-next-line */
        if (property_exists($this, 'criteria') && ! empty($this->criteria)) {
            foreach ($this->criteria as $criterion) {
                $query = $criterion->apply($query);
            }
            $this->criteria = [];
        }

        return $query;
    }

    /**
     * คืน Database Connection ของ Model
     *
     * ใช้สำหรับ transaction, raw query ที่ต้องการรันบน connection เดียวกับ Model
     * สำคัญมากสำหรับระบบที่ใช้หลาย database (multi-tenancy, read-replica)
     */
    protected function getConnection(): ConnectionInterface
    {
        return $this->model->getConnection();
    }
}
