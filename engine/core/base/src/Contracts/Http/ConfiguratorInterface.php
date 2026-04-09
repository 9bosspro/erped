<?php

declare(strict_types=1);

namespace Core\Base\Contracts\Http;

/**
 * ConfiguratorInterface — สัญญาพื้นฐานสำหรับ Configurator ทุกตัว
 *
 * กำหนดให้ทุก Configurator ต้องมีเมธอด configure() เพื่อให้
 * ServiceProvider เรียกใช้ได้อย่างสม่ำเสมอผ่าน polymorphism
 * โดยไม่ผูกกับ implementation จริง (Dependency Inversion Principle)
 */
interface ConfiguratorInterface
{
    /**
     * ดำเนินการตั้งค่าทั้งหมดที่รับผิดชอบ
     *
     * ควรเรียกจาก ServiceProvider::boot() เท่านั้น
     */
    public function configure(): void;
}
