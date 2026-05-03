<?php

declare(strict_types=1);

namespace {
    require_once __DIR__ . '/vendor/autoload.php';
    // โหลด Global Helpers
    $helperPaths = [
        __DIR__ . '/engine/helpers/AppHelper.php',
        __DIR__ . '/engine/core/base/helpers/CommonHelper.php',
        __DIR__ . '/engine/core/base/helpers/AppHelper.php',
        __DIR__ . '/engine/core/base/helpers/SupportHelper.php',
        __DIR__ . '/engine/core/base/helpers/action-filterHelper.php',
        __DIR__ . '/engine/core/base/helpers/labHelper.php',
        __DIR__ . '/engine/slave/helpers/SlaveHelper.php',
    ];

    foreach ($helperPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
