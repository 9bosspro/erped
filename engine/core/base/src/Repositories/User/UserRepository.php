<?php

declare(strict_types=1);

namespace Core\Base\Repositories\User;

use App\Models\AnonymousPeople;
use App\Models\ForeignersPeople;
use App\Models\People;
use App\Models\ThPeople;
use App\Models\TouristForeignersPeople;
use App\Models\User;
use Core\Base\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * UserRepository — Data Access Layer สำหรับ User และ People
 *
 * Extend BaseRepository เพื่อรับ operations มาตรฐานครบชุด:
 * - find(), findOrFail(), findMany(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), simplePaginate(), cursorPaginate(), lazy(), chunk()
 * - transaction(), lockForUpdate()
 * - withCriteria(), remember(), enableCache()
 * - beforeCreate(), afterCreate(), hooks ต่างๆ
 *
 * Repository นี้ implement เฉพาะ domain-specific methods ของ User/People
 *
 * ตัวอย่างการใช้งาน base methods:
 * ```php
 * // ค้นหา User จาก ID
 * $user = $repo->find($id);
 *
 * // สร้าง User ใหม่
 * $user = $repo->create(['email' => '...', 'password' => '...']);
 *
 * // Paginate พร้อม relations
 * $users = $repo->paginate(20, relations: ['people.peopleable']);
 * ```
 */
class UserRepository extends BaseRepository implements UserInterface
{
    /**
     * ลำดับ People model สำหรับค้นหาจาก citizen_id
     *
     * เรียงจาก model ที่พบบ่อยที่สุดก่อน เพื่อ performance:
     * AnonymousPeople → ThPeople → ForeignersPeople → TouristForeignersPeople
     *
     * @var array<int, class-string<Model>>
     */
    protected array $peopleModels = [
        AnonymousPeople::class,
        ThPeople::class,
        ForeignersPeople::class,
        TouristForeignersPeople::class,
    ];

    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    // =========================================================================
    // People Lookup
    // =========================================================================

    /**
     * ค้นหาข้อมูลบุคคลจากเลขประจำตัว ใน People model ทุกประเภท
     *
     * วนค้นทีละ model ตามลำดับ $peopleModels
     * ใช้ scope `byCitizenId()` ที่ต้องมีใน model แต่ละตัว
     *
     * @param  string  $citizenId  เลขประจำตัว
     * @return array{status: bool, model: class-string<Model>, data: mixed}
     */
    public function findPeopleByCitizenId(string $citizenId): array
    {
        foreach ($this->peopleModels as $modelClass) {
            $query = $modelClass::byCitizenId($citizenId);

            if ($query->exists()) {
                return [
                    'status' => true,
                    'model' => $modelClass,
                    'data' => $query,
                ];
            }
        }

        // ไม่พบในทุก model → คืนค่า default เป็น AnonymousPeople
        return [
            'status' => false,
            'model' => AnonymousPeople::class,
            'data' => null,
        ];
    }

    /**
     * ตรวจสอบว่า People record มีอยู่จาก citizen_id ผ่าน polymorphic whereHasMorph
     *
     * ค้นหาใน People table ที่มี peopleable (polymorphic) เป็น citizen_id นั้น
     * Eager load 'peopleable' เพื่อลด N+1 query เมื่อ caller ต้องการข้อมูลเพิ่ม
     *
     * @param  string  $citizenId  เลขประจำตัว
     * @return Model|null People model พร้อม 'peopleable' relation หรือ null
     */
    public function checkPeople(string $citizenId): ?Model
    {
        return People::whereHasMorph(
            'peopleable',
            $this->peopleModels,
            fn (Builder $query) => $query->where('citizen_id', $citizenId),
        )
            ->with('peopleable')
            ->first();
    }

    // =========================================================================
    // Uniqueness Checks
    // =========================================================================

    /**
     * ตรวจสอบว่า email มีในระบบแล้วหรือไม่
     *
     * ใช้ exists() ผ่าน BaseRepository (HasQueryOperations)
     * ไม่ COUNT(*) ทั้งตาราง — efficient
     *
     * @param  string  $email  อีเมลที่ต้องการตรวจสอบ
     * @return bool true = มีอยู่แล้ว
     */
    public function emailExists(string $email): bool
    {
        return $this->exists(fn (Builder $q) => $q->where('email', $email));
    }

    /**
     * ตรวจสอบว่า username มีในระบบแล้วหรือไม่
     *
     * @param  string  $username  username ที่ต้องการตรวจสอบ
     * @return bool true = มีอยู่แล้ว
     */
    public function usernameExists(string $username): bool
    {
        return $this->exists(fn (Builder $q) => $q->where('username', $username));
    }
}
