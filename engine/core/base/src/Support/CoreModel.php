<?php

declare(strict_types=1);

namespace Core\Base\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * CoreModel — Abstract Base Model สำหรับ modules ทั้งหมด
 *
 * เป็น extension point กลางสำหรับ:
 * - กำหนด default behaviors ร่วมกัน เช่น $guarded, $dateFormat
 * - เพิ่ม global scope ในอนาคต เช่น TenantScope, AuditScope
 * - Override Eloquent behaviors ในระดับ application layer
 *
 * การใช้งาน:
 * ```php
 * // Model ทั่วไป (auto-increment id)
 * class AuditLog extends CoreModel {
 *     protected $fillable = ['action', 'user_id'];
 * }
 *
 * // Model ที่ต้องการ UUID v7 — extend UuidModel แทน
 * class User extends UuidModel {
 *     protected $fillable = ['name', 'email'];
 * }
 *
 * // Model ที่ต้องการ SoftDelete — use trait ใน subclass
 * class Post extends UuidModel {
 *     use SoftDeletes;
 * }
 * ```
 *
 * หมายเหตุ: ไม่ include SoftDeletes และ HasUuids โดย default
 * เพราะไม่ใช่ทุก Model ต้องการ — ให้ subclass เลือกเอง
 */
abstract class CoreModel extends Model
{
    /**
     * ป้องกัน mass-assignment บน primary key โดย default
     *
     * Subclass กำหนด $fillable หรือ override $guarded เองตาม business logic
     *
     * @var array<string>
     */
    protected $guarded = ['id'];

    /**
     * รูปแบบ timestamp มาตรฐาน (ISO 8601 compatible)
     */
    protected $dateFormat = 'Y-m-d H:i:s';
}

/**
 * UuidModel — CoreModel ที่ใช้ UUID v7 (ordered) เป็น primary key
 *
 * ทำไมต้อง UUID v7 (orderedUuid)?
 * - Sequential ตาม time → B-tree index ไม่ fragmented (performance ใกล้ auto-increment)
 * - Distributed-safe → ไม่ต้องรอ DB generate ID (ลด round-trip)
 * - ตัว UUID มี timestamp ฝังอยู่ → debug/tracing ง่าย
 * - ป้องกัน enumeration attack (ต่างจาก sequential int ID)
 *
 * การใช้งาน:
 * ```php
 * class User extends UuidModel {
 *     protected $fillable = ['name', 'email'];
 * }
 *
 * // สร้าง record — id ถูก generate อัตโนมัติ
 * $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
 * echo $user->id; // "01960abc-xxxx-7xxx-xxxx-xxxxxxxxxxxx"
 * ```
 *
 * ⚠️ migration ต้องใช้ $table->uuid('id')->primary()
 *    ไม่ใช่ $table->id()
 */
abstract class UuidModel extends CoreModel
{
    use HasUuids;

    /**
     * Key ไม่ใช่ integer
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * สร้าง UUID v7 (time-ordered) เมื่อ creating
     *
     * Laravel's HasUuids จะเรียก newUniqueId() อัตโนมัติสำหรับทุก column
     * ที่อยู่ใน uniqueIds()
     */
    public function newUniqueId(): string
    {
        return (string) Str::orderedUuid();
    }

    /**
     * Columns ที่ต้องการให้ HasUuids auto-generate
     *
     * Override ใน subclass ถ้าต้องการ UUID บน column อื่นด้วย เช่น public_id
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }
}
