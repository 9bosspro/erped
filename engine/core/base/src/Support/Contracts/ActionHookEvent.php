<?php

declare(strict_types=1);

namespace Core\Base\Support\Contracts;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Abstract base สำหรับระบบ Hook / Filter Events
 *
 * ความรับผิดชอบ:
 *  - จัดการ lifecycle ของ listeners (add / remove / query)
 *  - ไม่กำหนด fire strategy — ให้ subclass (Action, Filter) รับผิดชอบ
 *
 * หลักการออกแบบ:
 *  - เก็บ listeners เป็น [hook][priority][] เพื่อรองรับหลาย callback ต่อ priority เดียวกัน
 *  - แยก raw listeners (write path) จาก sorted cache (read path)
 *  - Lazy rebuild cache เมื่อมีการเปลี่ยนแปลง (cacheDirty flag)
 *  - Dependency-injection container เพื่อหลีกเลี่ยงการ couple กับ global app()
 *  - ไม่ใช้ SplPriorityQueue เพราะไม่รองรับ random-access delete
 *
 * โครงสร้างข้อมูล:
 * ```
 * listeners[hook][priority][] = ListenerData
 * listenerCache[hook][]       = ListenerData (sorted ascending by priority)
 * ```
 *
 * @phpstan-type ListenerData array{
 *     id: string,
 *     callback: callable,
 *     arguments: int,
 *     once: bool,
 *     scope: string|null
 * }
 */
abstract class ActionHookEvent
{
    /**
     * Raw listeners จัดกลุ่มตาม hook → priority → list
     *
     * โครงสร้าง: listeners[hook][priority][] = ListenerData
     *
     * @var array<string, array<int, list<array<string, mixed>>>>
     */
    protected array $listeners = [];

    /**
     * Sorted flat list สำหรับ iteration — rebuild เมื่อ cacheDirty=true
     *
     * โครงสร้าง: listenerCache[hook][] = ListenerData (เรียงตาม priority น้อยไปมาก)
     *
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $listenerCache = [];

    /**
     * Flag ระบุว่า cache ต้องถูก rebuild หรือไม่
     */
    protected bool $cacheDirty = false;

    /**
     * @param  Container|null  $app  IoC container — ใช้ resolve callback แบบ "Class@method"
     *                               ถ้า null จะ fallback ไปใช้ global app() helper
     */
    public function __construct(
        protected ?Container $app = null,
    ) {}

    /**
     * ลงทะเบียน callback สำหรับ hook(s)
     *
     * รองรับ multiple callbacks ต่อ priority เดียวกัน
     *
     * @param  array<string>|string  $hook      ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority   ลำดับ (น้อย = รันก่อน, default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  bool  $once      true = รันครั้งเดียวแล้วลบออกอัตโนมัติ
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'api', 'web'
     *
     * @throws InvalidArgumentException ถ้า hook ว่างเปล่า
     */
    public function addListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
    ): void {
        if (empty($hook)) {
            throw new InvalidArgumentException('Hook name cannot be empty.');
        }

        $listenerData = [
            'id'        => Uuid::uuid7()->toString(),
            'callback'  => $this->resolveCallback($callback),
            'arguments' => max(1, $arguments),
            'once'      => $once,
            'scope'     => $scope,
        ];

        foreach ((array) $hook as $hookName) {
            if (! is_string($hookName) || $hookName === '') {
                continue;
            }

            $this->listeners[$hookName][$priority][] = $listenerData;
            $this->cacheDirty = true;
        }
    }

    /**
     * ลงทะเบียน callback แบบ one-shot (รันครั้งเดียวแล้วลบอัตโนมัติ)
     *
     * Shortcut ของ addListener(..., once: true)
     *
     * @param  array<string>|string  $hook      ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority   ลำดับ (default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  string|null  $scope  จำกัด scope
     *
     * @throws InvalidArgumentException ถ้า hook ว่างเปล่า
     */
    public function addOnceListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): void {
        $this->addListener($hook, $callback, $priority, $arguments, once: true, scope: $scope);
    }

    /**
     * ลบ listeners ทั้งหมดของ hook หรือลบเฉพาะ listener ที่ระบุ id
     *
     * @param  array<string>|string|null  $hook  null = ลบ listeners ทั้งหมดทุก hook
     * @param  string|null  $id  UUID ของ listener ที่ต้องการลบเฉพาะตัว
     */
    public function removeListener(string|array|null $hook = null, ?string $id = null): static
    {
        if ($hook === null) {
            $this->listeners    = [];
            $this->listenerCache = [];
            $this->cacheDirty   = false;

            return $this;
        }

        foreach ((array) $hook as $hookName) {
            if (! is_string($hookName) || ! isset($this->listeners[$hookName])) {
                continue;
            }

            if ($id === null) {
                unset($this->listeners[$hookName], $this->listenerCache[$hookName]);
            } else {
                foreach ($this->listeners[$hookName] as $priorityKey => $group) {
                    $filtered = array_values(
                        array_filter($group, fn ($l) => $l['id'] !== $id),
                    );

                    if (empty($filtered)) {
                        unset($this->listeners[$hookName][$priorityKey]);
                    } else {
                        $this->listeners[$hookName][$priorityKey] = $filtered;
                    }
                }

                if (empty($this->listeners[$hookName])) {
                    unset($this->listeners[$hookName], $this->listenerCache[$hookName]);
                }
            }

            $this->cacheDirty = true;
        }

        return $this;
    }

    /**
     * ตรวจสอบว่า hook มี listeners หรือไม่
     *
     * @param  string|null  $hook   null = ตรวจสอบว่ามี listener อยู่เลยไหม (ทุก hook)
     * @param  string|null  $scope  กรอง scope (null = ไม่กรอง)
     */
    public function hasListeners(?string $hook = null, ?string $scope = null): bool
    {
        if ($this->cacheDirty) {
            $this->rebuildCache();
        }

        if ($hook === null) {
            return ! empty($this->listenerCache);
        }

        if (! isset($this->listenerCache[$hook])) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        return ! empty(array_filter(
            $this->listenerCache[$hook],
            fn ($l) => $l['scope'] === null || $l['scope'] === $scope,
        ));
    }

    /**
     * คืน listeners สำหรับ hook ที่ระบุ พร้อมกรอง scope ถ้าระบุ
     *
     * @param  string  $hook        ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = คืนทั้งหมด)
     * @return list<array<string, mixed>>
     */
    public function getListeners(string $hook, ?string $scope = null): array
    {
        if ($this->cacheDirty) {
            $this->rebuildCache();
        }

        $listeners = $this->listenerCache[$hook] ?? [];

        if ($scope === null) {
            return $listeners;
        }

        return array_values(array_filter(
            $listeners,
            fn ($l) => $l['scope'] === null || $l['scope'] === $scope,
        ));
    }

    /**
     * นับจำนวน listeners
     *
     * @param  string|null  $hook   null = นับทั้งหมดทุก hook
     * @param  string|null  $scope  กรอง scope (null = ไม่กรอง)
     */
    public function getListenerCount(?string $hook = null, ?string $scope = null): int
    {
        if ($hook !== null) {
            return count($this->getListeners($hook, $scope));
        }

        if ($this->cacheDirty) {
            $this->rebuildCache();
        }

        return (int) array_sum(array_map('count', $this->listenerCache));
    }

    /**
     * Rebuild sorted cache จาก raw listeners
     *
     * - เรียง priority ascending (น้อย → มาก) — priority ต่ำ รันก่อน
     * - Flatten [priority][index] → flat list เพื่อ iteration ง่ายขึ้น
     * - เรียกอัตโนมัติเมื่อ cacheDirty=true
     */
    protected function rebuildCache(): void
    {
        $this->listenerCache = [];

        foreach ($this->listeners as $hookName => $priorities) {
            ksort($priorities);

            $flat = [];
            foreach ($priorities as $group) {
                foreach ($group as $listener) {
                    $flat[] = $listener;
                }
            }

            $this->listenerCache[$hookName] = $flat;
        }

        $this->cacheDirty = false;
    }

    /**
     * ลบ listener ออกหลังรัน (ใช้ภายในสำหรับ once=true)
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string  $id    UUID ของ listener
     */
    protected function removeOnceListener(string $hook, string $id): void
    {
        $this->removeListener($hook, $id);
    }

    /**
     * Resolve callback ให้อยู่ในรูปที่ call_user_func_array รับได้
     *
     * รองรับ:
     *  - Closure
     *  - callable (function name, [object, method], [class, method])
     *  - 'ClassName@method' string — resolve instance ผ่าน IoC container
     *  - [ClassName::class, 'method'] array — resolve instance ผ่าน IoC container
     *
     * @throws InvalidArgumentException ถ้า callback ไม่ถูกต้อง หรือ method ไม่มีอยู่
     */
    protected function resolveCallback(callable|Closure|array|string $callback): callable
    {
        if ($callback instanceof Closure || is_callable($callback)) {
            return $callback;
        }

        $container = $this->app ?? app();

        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $instance = $container->make($class);

            if (! method_exists($instance, $method)) {
                throw new InvalidArgumentException("Method [{$class}@{$method}] does not exist.");
            }

            return [$instance, $method];
        }

        if (is_array($callback) && count($callback) === 2) {
            [$classOrObject, $method] = $callback;
            $instance = is_object($classOrObject) ? $classOrObject : $container->make($classOrObject);

            if (! method_exists($instance, $method)) {
                throw new InvalidArgumentException("Method [{$method}] does not exist on class.");
            }

            return [$instance, $method];
        }

        throw new InvalidArgumentException(
            'Invalid callback format. Use Closure, callable, "Class@method", or [Class, method].',
        );
    }
}
