<?php

declare(strict_types=1);

namespace Core\Base\Facades;

use Closure;
use Core\Base\Support\Action as ActionConcrete;
use Illuminate\Support\Facades\Facade;

/**
 * Facade สำหรับ Action Hook System
 *
 * @method static void addListener(string|array $hook, callable|Closure|array|string $callback, int $priority = 10, int $arguments = 1, bool $once = false, string|null $scope = null)
 * @method static void addOnceListener(string|array $hook, callable|Closure|array|string $callback, int $priority = 10, int $arguments = 1, string|null $scope = null)
 * @method static static removeListener(string|array|null $hook = null, string|null $id = null)
 * @method static void fire(string $hook, array $args = [], string|null $scope = null)
 * @method static bool hasListeners(string|null $hook = null, string|null $scope = null)
 * @method static array getListeners(string $hook, string|null $scope = null)
 * @method static int getListenerCount(string|null $hook = null, string|null $scope = null)
 *
 * @see ActionConcrete
 */
class Action extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'core.action';
    }
}
