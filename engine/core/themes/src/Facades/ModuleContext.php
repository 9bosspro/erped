<?php

declare(strict_types=1);

namespace Core\Themes\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null getCurrentModule()
 * @method static bool isCurrentModule(string $moduleName)
 * @method static array{name: string, path: string, enabled: bool}|null getCurrentModuleInfo()
 * @method static bool setThemes(?string $themeName, ?string $type = 'frontend')
 *
 * @see \Core\Themes\Services\ModuleContextService
 */
class ModuleContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'module.context';
    }
}
