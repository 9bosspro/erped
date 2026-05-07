<?php

use Engine\Modules\Demo\Http\Controllers\DemoController;
use Engine\Modules\Demo\Http\Controllers\LayoutDemoController;
use Illuminate\Support\Facades\Route;

Route::middleware([])->group(function () {
    Route::resource('demos', DemoController::class)->names('demo');
});

Route::group(
    ['prefix' => 'demos/lab', 'as' => 'demo.lab.'],
    __DIR__ . '/web/lab.php',
);

Route::prefix('layout-demo')->name('layout-demo.')->group(function () {
    // Layout demos
    Route::get('/frontend',   [LayoutDemoController::class, 'frontend'])->name('frontend');
    Route::get('/auth',       [LayoutDemoController::class, 'auth'])->name('auth');
    Route::get('/fullscreen', [LayoutDemoController::class, 'fullscreen'])->name('fullscreen');
    Route::get('/bare',       [LayoutDemoController::class, 'bare'])->name('bare');

    // Media demos
    Route::get('/gallery', [LayoutDemoController::class, 'gallery'])->name('gallery');
    Route::get('/youtube', [LayoutDemoController::class, 'youtube'])->name('youtube');
    Route::get('/music',   [LayoutDemoController::class, 'music'])->name('music');

    // หน้า backend ต้องการ auth
    Route::middleware(['auth'])->group(function () {
        Route::get('/backend', [LayoutDemoController::class, 'backend'])->name('backend');
    });
});
