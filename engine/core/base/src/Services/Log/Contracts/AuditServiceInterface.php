<?php

declare(strict_types=1);

namespace Core\Base\Services\Log\Contracts;

/**
 * AuditServiceInterface — สัญญาสำหรับระบบ Audit Trail ระดับ Enterprise
 */
interface AuditServiceInterface
{
    /**
     * บันทึกเหตุการณ์การเปลี่ยนแปลงข้อมูล (Audit Event)
     *
     * @param  string  $event  ชื่อเหตุการณ์ (เช่น 'user.update', 'order.deleted')
     * @param  string  $subject  สิ่งที่ถูกกระทำ (เช่น 'User#123', 'PaymentRecord')
     * @param  array<string, mixed>  $data  ข้อมูลประกอบหรือการเปลี่ยนแปลง
     * @param  string|null  $module  ชื่อ module ที่ลดภาระการตรวจสอบต้นทาง
     */
    public function log(string $event, string $subject = '', array $data = [], ?string $module = null): void;

    /**
     * บันทึกความผิดพลาดที่เกี่ยวข้องกับความปลอดภัย
     */
    public function logSecurity(string $event, array $data = []): void;
}
