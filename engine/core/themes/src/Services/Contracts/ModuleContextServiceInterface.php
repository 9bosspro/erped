<?php

declare(strict_types=1);

namespace Core\Themes\Services\Contracts;

/**
 * ModuleContextServiceInterface — สัญญาสำหรับ module context และ theme management
 *
 * กำหนด contract สำหรับตรวจหา module ปัจจุบันและจัดการ theme
 * ช่วยให้ swap implementation และ mock ใน test ได้โดยไม่แก้โค้ดผู้ใช้
 */
interface ModuleContextServiceInterface
{
    /**
     * ดึงชื่อ module ปัจจุบันจาก Route
     *
     * ลำดับการตรวจ: Controller namespace → Route name → URI segment
     * ผลลัพธ์ถูก cache ต่อ request เพื่อป้องกัน Module::find() ซ้ำ
     *
     * @return string|null ชื่อ module หรือ null ถ้าไม่พบ
     */
    public function getCurrentModule(): ?string;

    /**
     * ตั้งค่าชื่อ module ปัจจุบันด้วยตนเอง (Manual Override)
     */
    public function setCurrentModule(?string $moduleName): void;

    /**
     * ตรวจสอบว่า module ที่ระบุเป็น module ปัจจุบันหรือไม่
     *
     * @param  string  $moduleName  ชื่อ module ที่ต้องการเปรียบเทียบ
     */
    public function isCurrentModule(string $moduleName): bool;

    /**
     * ดึง metadata ของ module ปัจจุบัน
     *
     * @return array{name: string, path: string, enabled: bool}|null
     */
    public function getCurrentModuleInfo(): ?array;

    /**
     * ตั้งค่า theme และ register view paths ของ module ปัจจุบัน
     *
     * @param  string|null  $themeName  ชื่อ theme (เช่น 'system')
     * @param  string|null  $type  ประเภท theme (เช่น 'frontend', 'backend')
     * @return bool true ถ้า register view paths สำเร็จ
     */
    public function setThemes(?string $themeName, ?string $type = 'frontend'): bool;
}
