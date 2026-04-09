<?php

$modularProviders = file_exists($path = __DIR__ . '/../engine/core/base/providers.php')
    ? require $path
    : [];

return array_merge([
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    Slave\Providers\SlaveServiceProvider::class,
], $modularProviders);
