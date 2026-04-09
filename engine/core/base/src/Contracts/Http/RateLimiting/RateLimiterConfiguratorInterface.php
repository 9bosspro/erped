<?php

declare(strict_types=1);

namespace Core\Base\Contracts\Http\RateLimiting;

use Core\Base\Contracts\Http\ConfiguratorInterface;

/**
 * RateLimiterConfiguratorInterface — สัญญาสำหรับการตั้งค่า Rate Limiting
 *
 * Extend ConfiguratorInterface เพื่อจำกัดความรับผิดชอบเฉพาะ rate limiting เท่านั้น
 * ช่วยให้ mock ใน Feature Test และสลับ strategy ได้โดยไม่กระทบ ServiceProvider
 */
interface RateLimiterConfiguratorInterface extends ConfiguratorInterface {}
