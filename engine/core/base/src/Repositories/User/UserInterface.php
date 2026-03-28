<?php

declare(strict_types=1);

namespace Core\Base\Repositories\User;

use Core\Base\Repositories\Interfaces\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * UserInterface — สัญญา Repository สำหรับ User และ People data
 *
 * Extend BaseRepositoryInterface เพื่อรับ operations มาตรฐานทั้งหมด:
 * - find(), findMany(), findOrFail(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), lazy(), chunk()
 * - transaction(), lockForUpdate()
 * - cache, criteria, hooks
 *
 * Interface นี้ประกาศเฉพาะ domain-specific methods ของ User/People เท่านั้น
 *
 * หมายเหตุ: User มีความสัมพันธ์แบบ polymorphic กับ People ผ่าน peopleable
 * รองรับหลาย People model: AnonymousPeople, ThPeople, ForeignersPeople, TouristForeignersPeople
 */
interface UserInterface extends BaseRepositoryInterface
{
    // =========================================================================
    // People Lookup — ค้นหาผ่าน polymorphic peopleable
    // =========================================================================

    /**
     * ค้นหาข้อมูลบุคคลจากเลขประจำตัว ใน People model ทุกประเภท
     *
     * ค้นตามลำดับ: AnonymousPeople → ThPeople → ForeignersPeople → TouristForeignersPeople
     * คืน model ตัวแรกที่พบ — ถ้าไม่พบเลย status = false, data = null
     *
     * @param  string  $citizenId  เลขประจำตัว (citizen_id)
     * @return array{status: bool, model: class-string<Model>, data: mixed}
     */
    public function findPeopleByCitizenId(string $citizenId): array;

    /**
     * ตรวจสอบว่า People record มีอยู่จาก citizen_id (ผ่าน polymorphic whereHasMorph)
     *
     * คืน People model พร้อม relation 'peopleable' ถ้าพบ
     * คืน null ถ้าไม่พบ People ที่มี citizen_id นั้น
     *
     * @param  string  $citizenId  เลขประจำตัว
     * @return Model|null People model หรือ null
     */
    public function checkPeople(string $citizenId): ?Model;

    // =========================================================================
    // Uniqueness Checks — ใช้ก่อนสร้าง User ใหม่
    // =========================================================================

    /**
     * ตรวจสอบว่า email มีในระบบแล้วหรือไม่
     *
     * ใช้สำหรับ validation ก่อนลงทะเบียน เพื่อป้องกัน email ซ้ำ
     * ไม่รวม soft-deleted users (เพราะ email ที่ถูกลบถือว่าว่างแล้ว)
     *
     * @param  string  $email  อีเมลที่ต้องการตรวจสอบ
     * @return bool true = มีอยู่แล้ว (ห้ามใช้ซ้ำ), false = ว่าง (ใช้ได้)
     */
    public function emailExists(string $email): bool;

    /**
     * ตรวจสอบว่า username มีในระบบแล้วหรือไม่
     *
     * ใช้สำหรับ validation ก่อนสร้าง User
     *
     * @param  string  $username  username ที่ต้องการตรวจสอบ
     * @return bool true = มีอยู่แล้ว, false = ว่าง
     */
    public function usernameExists(string $username): bool;
}
