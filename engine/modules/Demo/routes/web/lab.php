<?php

use Engine\Modules\Demo\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        // 'middleware' => ['auth'],
    ],
    function () {
        // หน้า Lab — Inertia page (GET /lab)
        Route::get('/', [LabController::class, 'index'])->name('index');

        // JSON endpoint: ทดสอบ key generation
        Route::get('/lab1', [LabController::class, 'lab1'])->name('lab1');
    },
);
