<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Support\Arrayable;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * BaseInertiaController — Controller ฐานสำหรับ render Inertia page อย่างปลอดภัยและสอดคล้องทั้งระบบ
 *
 * ใช้ในทุก module ที่ return Inertia เพื่อ:
 *  - มี contract เดียวกัน (subclass override $pagePrefix เพื่อชี้โฟลเดอร์ pages ของ module นั้น)
 *  - ระบุ layout area ผ่าน $layout ('backend' | 'frontend')
 *  - ลด boilerplate ของการเรียก Inertia::render()
 *
 * ตัวอย่าง backend module (default):
 *   class AdminController extends BaseInertiaController
 *   {
 *       protected string $pagePrefix = 'admin';
 *       // $layout = 'backend' โดย default
 *   }
 *
 * ตัวอย่าง frontend module:
 *   class UserController extends BaseInertiaController
 *   {
 *       protected string $pagePrefix = 'user';
 *       protected string $layout     = 'frontend';
 *   }
 *
 * ตัวอย่าง override layout per-call:
 *   $this->inertia('some/page', ['_layout' => 'frontend', 'data' => $data]);
 */
abstract class BaseInertiaController extends Controller
{
    /**
     * Prefix โฟลเดอร์ pages ของ module — subclass override ได้
     * เช่น 'demo' → page 'lab/index' จะ resolve เป็น 'demo/lab/index'
     */
    protected string $pagePrefix = '';

    /**
     * Layout area default: 'backend' (sidebar) | 'frontend' (navbar+footer)
     * DynamicLayout ฝั่ง React อ่านค่านี้เพื่อเลือก layout component
     * override per-call ได้โดยส่ง '_layout' ใน $props
     */
    protected string $layout = 'backend';

    /**
     * Render Inertia page — แนบ _layout ให้อัตโนมัติ
     *
     * @param  array<string, mixed>|Arrayable<string, mixed>  $props
     */
    protected function inertia(string $page, array|Arrayable $props = []): InertiaResponse
    {
        $component = $this->resolveComponent($page);
        $payload   = $props instanceof Arrayable ? $props->toArray() : $props;

        // ให้ props override $this->layout ได้ต่อ call
        $layout = $payload['_layout'] ?? $this->layout;

        return Inertia::render($component, ['_layout' => $layout, ...$payload]);
    }

    /**
     * รวม page name กับ pagePrefix ของ module
     *
     * - $page ขึ้นต้นด้วย '/' → ใช้ path เต็มตามที่ระบุ (bypass prefix)
     * - pagePrefix ว่าง → คืน $page ตามเดิม
     */
    private function resolveComponent(string $page): string
    {
        if (str_starts_with($page, '/')) {
            return ltrim($page, '/');
        }

        if ($this->pagePrefix === '') {
            return $page;
        }

        return trim($this->pagePrefix, '/') . '/' . $page;
    }
}
