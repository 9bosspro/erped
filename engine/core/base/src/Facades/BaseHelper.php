<?php

namespace Core\Base\Facades;

use Core\Base\Support\Helpers\App\AppContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{controller: string, action: string}|null getControllerAction()
 * @method static string|null getAppKey()
 * @method static bool isActiveRoute(string|array $routeNames)
 * @method static string|null getCurrentGuard(string[] $guards = ['web', 'api'])
 * @method static bool isApiRequest()
 * @method static string generateSlug(string $string)
 * @method static string extractBaseDomain(string $url)
 *
 * @see AppContext
 */
class BaseHelper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AppContext::class;
    }
}
