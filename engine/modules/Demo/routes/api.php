<?php

use Illuminate\Support\Facades\Route;
use Engine\Modules\Demo\Http\Controllers\DemoController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('demos', DemoController::class)->names('demo');
});
