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
 *  - Per-hook dirty tracking — rebuild เฉพาะ hook ที่เปลี่ยนแปลง ไม่ใช่ทั้งหมด
 *  - Dependency-injection container เพื่อหลีกเลี่ยงการ couple กับ global app()
 *  - ไม่ใช้ SplPriorityQueue เพราะไม่รองรับ random-access delete
 *
 * โครงสร้างข้อมูล:
 * ```
 * listeners[hook][priority][] = ListenerData
 * listenerCache[hook][]       = ListenerData (sorted ascending by priority)
 * dirtyHooks[hook]            = true  (เฉพาะ hook ที่ต้อง rebuild)
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
     * @var array<string, array<int, list<ListenerData>>>
     */
    protected array $listeners = [];

    /**
     * Sorted flat list สำหรับ iteration — rebuild เมื่อ hook อยู่ใน dirtyHooks
     *
     * @var array<string, list<ListenerData>>
     */
    protected array $listenerCache = [];

    /**
     * Hook names ที่ต้อง rebuild cache — per-hook granularity
     *
     * @var array<string, true>
     */
    protected array $dirtyHooks = [];

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
     * @param  array<string>|string  $hook  ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (น้อย = รันก่อน, default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  bool  $once  true = รันครั้งเดียวแล้วลบออกอัตโนมัติ
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'api', 'web'
     * @return string UUID ของ listener — ใช้ removeListener() เพื่อลบ
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
    ): string {
        if (empty($hook)) {
            throw new InvalidArgumentException('Hook name cannot be empty.');
        }

        $id = Uuid::uuid7()->toString();

        $listenerData = [
            'id' => $id,
            'callback' => $this->resolveCallback($callback),
            'arguments' => max(1, $arguments),
            'once' => $once,
            'scope' => $scope,
        ];

        foreach ((array) $hook as $hookName) {
            if (! \is_string($hookName) || $hookName === '') {
                continue;
            }

            $this->listeners[$hookName][$priority][] = $listenerData;
            $this->dirtyHooks[$hookName] = true;
        }

        return $id;
    }

    /**
     * ลงทะเบียน callback แบบ one-shot (รันครั้งเดียวแล้วลบอัตโนมัติ)
     *
     * @param  array<string>|string  $hook  ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  string|null  $scope  จำกัด scope
     * @return string UUID ของ listener
     *
     * @throws InvalidArgumentException ถ้า hook ว่างเปล่า
     */
    public function addOnceListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): string {
        return $this->addListener($hook, $callback, $priority, $arguments, once: true, scope: $scope);
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
            $this->listeners = [];
            $this->listenerCache = [];
            $this->dirtyHooks = [];

            return $this;
        }

        foreach ((array) $hook as $hookName) {
            if (! \is_string($hookName) || ! isset($this->listeners[$hookName])) {
                continue;
            }

            if ($id === null) {
                unset($this->listeners[$hookName], $this->listenerCache[$hookName]);
            } else {
                foreach ($this->listeners[$hookName] as $priorityKey => $group) {
                    $filtered = \array_values(
                        \array_filter($group, fn ($l) => $l['id'] !== $id),
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

            $this->dirtyHooks[$hookName] = true;
        }

        return $this;
    }

    /**
     * ตรวจสอบว่า hook มี listeners หรือไม่
     *
     * @param  string|null  $hook  null = ตรวจสอบว่ามี listener อยู่เลยไหม (ทุก hook)
     * @param  string|null  $scope  กรอง scope (null = ไม่กรอง)
     */
    public function hasListeners(?string $hook = null, ?string $scope = null): bool
    {
        $this->ensureCacheUpToDate($hook);

        if ($hook === null) {
            return ! empty($this->listenerCache);
        }

        if (! isset($this->listenerCache[$hook])) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        return ! empty(\array_filter(
            $this->listenerCache[$hook],
            fn ($l) => $l['scope'] === null || $l['scope'] === $scope,
        ));
    }

    /**
     * คืน listeners สำหรับ hook ที่ระบุ พร้อมกรอง scope ถ้าระบุ
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = คืนทั้งหมด)
     * @return list<array<string, mixed>>
     */
    public function getListeners(string $hook, ?string $scope = null): array
    {
        $this->ensureCacheUpToDate($hook);

        $listeners = $this->listenerCache[$hook] ?? [];

        if ($scope === null) {
            return $listeners;
        }

        return \array_values(\array_filter(
            $listeners,
            fn ($l) => $l['scope'] === null || $l['scope'] === $scope,
        ));
    }

    /**
     * นับจำนวน listeners
     *
     * @param  string|null  $hook  null = นับทั้งหมดทุก hook
     * @param  string|null  $scope  กรอง scope (null = ไม่กรอง)
     */
    public function getListenerCount(?string $hook = null, ?string $scope = null): int
    {
        if ($hook !== null) {
            return count($this->getListeners($hook, $scope));
        }

        $this->ensureCacheUpToDate(null);

        return (int) \array_sum(\array_map('\count', $this->listenerCache));
    }

    /**
     * เรียก callback ของ listener พร้อมส่ง arguments ที่ถูกต้อง
     *
     * @param  array<string, mixed>  $listener  ListenerData
     * @param  array<mixed>  $args  arguments ปัจจุบัน
     * @return mixed ผลลัพธ์จาก callback
     */
    protected function invokeListener(array $listener, array $args): mixed
    {
        $count = \is_int($listener['arguments'] ?? null) ? \max(1, $listener['arguments']) : 1;
        $parameters = \array_slice($args, 0, $count);

        /** @var mixed $callback */
        $callback = $listener['callback'] ?? null;

        return \is_callable($callback) ? \call_user_func_array($callback, $parameters) : null;
    }

    /**
     * Ensure cache is up-to-date สำหรับ hook ที่ระบุ (หรือทุก hook ถ้า null)
     *
     * @param  string|null  $hook  hook ที่ต้องการ หรือ null สำหรับทุก hook
     */
    protected function ensureCacheUpToDate(?string $hook): void
    {
        if ($this->dirtyHooks === []) {
            return;
        }

        if ($hook !== null && isset($this->dirtyHooks[$hook])) {
            $this->rebuildCacheForHook($hook);
            unset($this->dirtyHooks[$hook]);

            return;
        }

        if ($hook === null) {
            $this->rebuildCache();
        }
    }

    /**
     * Rebuild sorted cache สำหรับ hook เดียว
     */
    protected function rebuildCacheForHook(string $hook): void
    {
        if (! isset($this->listeners[$hook])) {
            unset($this->listenerCache[$hook]);

            return;
        }

        $priorities = $this->listeners[$hook];
        ksort($priorities);

        $flat = [];
        foreach ($priorities as $group) {
            foreach ($group as $listener) {
                $flat[] = $listener;
            }
        }

        $this->listenerCache[$hook] = $flat;
    }

    /**
     * Rebuild sorted cache จาก raw listeners (ทุก dirty hooks)
     *
     * - เรียง priority ascending (น้อย → มาก) — priority ต่ำ รันก่อน
     * - เรียกอัตโนมัติเมื่อมี dirtyHooks และ hook=null ถูกถาม
     */
    protected function rebuildCache(): void
    {
        foreach ($this->dirtyHooks as $hookName => $_) {
            $this->rebuildCacheForHook($hookName);
        }

        $this->dirtyHooks = [];
    }

    /**
     * ลบ listener ออกหลังรัน (ใช้ภายในสำหรับ once=true)
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string  $id  UUID ของ listener
     */
    protected function removeOnceListener(string $hook, string $id): void
    {
        $this->removeListener($hook, $id);
    }

    /**
     * Resolve callback ให้อยู่ในรูปที่ call_user_func_array รับได้
     *
     * ลำดับการตรวจสอบ:
     *  1. Closure                — คืนตรง
     *  2. 'ClassName@method'     — resolve instance ผ่าน IoC แล้วคืน [instance, method]
     *  3. [Class, method]        — resolve instance ผ่าน IoC แล้วคืน [instance, method]
     *  4. [object, method]       — มี instance แล้ว คืนตรง
     *  5. callable อื่นๆ         — function name, static method — คืนตรง
     *
     * หมายเหตุ: ['ClassName', 'method'] ต้องผ่าน IoC (ไม่ใช่ is_callable early-return)
     * เพื่อให้ได้ instance ที่ถูก inject dependencies อย่างถูกต้อง
     *
     * @throws InvalidArgumentException ถ้า callback ไม่ถูกต้อง หรือ method ไม่มีอยู่
     */
    protected function resolveCallback(callable|Closure|array|string $callback): callable
    {
        // 1. Closure — คืนตรงโดยไม่ต้องผ่าน IoC
        if ($callback instanceof Closure) {
            return $callback;
        }

        $container = $this->app ?? app();

        // 2. 'ClassName@method' — resolve ผ่าน IoC
        if (\is_string($callback) && \str_contains($callback, '@')) {
            [$class, $method] = \explode('@', $callback, 2);
            $instance = $container->make($class);

            if (! \method_exists($instance, $method)) {
                throw new InvalidArgumentException("Method [{$class}@{$method}] does not exist.");
            }

            return [$instance, $method];
        }

        // 3 & 4. [ClassName, method] หรือ [object, method]
        if (\is_array($callback) && \count($callback) === 2) {
            [$classOrObject, $method] = $callback;

            // [object, method] — มี instance แล้ว ไม่ต้อง resolve
            if (\is_object($classOrObject)) {
                if (! \method_exists($classOrObject, $method)) {
                    throw new InvalidArgumentException("Method [{$method}] does not exist on object.");
                }

                return [$classOrObject, $method];
            }

            // [ClassName, method] — resolve instance ผ่าน IoC (รองรับ non-static + DI)
            $instance = $container->make((string) $classOrObject);

            if (! \method_exists($instance, $method)) {
                throw new InvalidArgumentException("Method [{$classOrObject}@{$method}] does not exist.");
            }

            return [$instance, $method];
        }

        // 5. callable อื่นๆ — function name, static callable
        if (\is_callable($callback)) {
            return $callback;
        }

        throw new InvalidArgumentException(
            'Invalid callback format. Use Closure, callable, "Class@method", or [Class, method].',
        );
    }
}
