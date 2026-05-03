<?php

declare(strict_types=1);

namespace Core\Base\Support\DTOs;

/**
 * Data Transfer Object สำหรับระบบ Audit Trail
 * ใช้แพ็กข้อมูลเหตุการณ์สำคัญที่มีความ Sensitive สูง ให้อยู่ในรูปแบบ Strongly-Typed
 */
final readonly class SensitiveAccessDTO
{
    /**
     * @param  string  $actionName  ชื่อการกระทำหรือชื่อทรัพยากรที่ถูกดึง (เช่น 'Secret Lab Resources')
     * @param  string  $reason  เหตุผลที่เข้าถึง (เช่น 'demonstration')
     * @param  array<string, mixed>  $extraData  ข้อมูลเพิ่มเติมอื่นๆ ที่เกี่ยวข้อง
     */
    public function __construct(
        public string $actionName,
        public string $reason,
        public array $extraData = [],
    ) {}

    /**
     * แปลงเป็น Array เพื่อส่งเข้า Legacy Logger หรือ Action Queue
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action_name' => $this->actionName,
            'reason' => $this->reason,
            'extra_data' => $this->extraData,
        ];
    }
}
