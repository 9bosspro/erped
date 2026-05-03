<?php

declare(strict_types=1);

namespace Core\Base\Facades;

use Closure;
use Core\Base\Support\Helpers\Cms\Filter as FilterConcrete;
use Illuminate\Support\Facades\Facade;

/**
 * Facade สำหรับ Filter Hook System
 *
 * @method static string addListener(string|array $hook, callable|Closure|array|string $callback, int $priority = 10, int $arguments = 1, bool $once = false, string|null $scope = null)
 * @method static string addOnceListener(string|array $hook, callable|Closure|array|string $callback, int $priority = 10, int $arguments = 1, string|null $scope = null)
 * @method static static removeListener(string|array|null $hook = null, string|null $id = null)
 * @method static mixed fire(string $hook, array $args = [], string|null $scope = null)
 * @method static bool hasListeners(string|null $hook = null, string|null $scope = null)
 * @method static list<array<string, mixed>> getListeners(string $hook, string|null $scope = null)
 * @method static int getListenerCount(string|null $hook = null, string|null $scope = null)
 *
 * @see FilterConcrete
 */
class Filter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'core.filter';
    }
}
