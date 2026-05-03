<?php

declare(strict_types=1);

namespace Slave\Facades;

use Illuminate\Support\Facades\Facade;
use Slave\Contracts\Master\MasterClientInterface;

/**
 * MasterClient Facade
 *
 * @method static array<string, mixed> get(string $endpoint, array<string, mixed> $query = [])
 * @method static array<string, mixed> post(string $endpoint, array<string, mixed> $data = [])
 * @method static bool ping()
 * @method static string getBaseUrl()
 *
 * @see MasterClientInterface
 */
class MasterClient extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MasterClientInterface::class;
    }
}
