<?php

use Engine\Modules\Demo\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        // 'prefix' => 'demo',
        //   'middleware' => ['auth'],
        // 'as' => 'demo.',
    ],
    function () {
        Route::get('/lab1', [LabController::class, 'lab1'])->name(
            'lab1',
        );
    },
);
