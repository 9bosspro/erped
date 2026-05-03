<?php

use Illuminate\Support\Facades\Route;
use Engine\Modules\Demo\Http\Controllers\DemoController;

/* Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('demos', DemoController::class)->names('demo');
}); */

Route::middleware([])->group(function () {
    Route::resource('demos', DemoController::class)->names('demo');
});
Route::group(
    ['prefix' => 'lab', 'as' => 'lab.'],
    __DIR__ . '/web/lab.php',
);
