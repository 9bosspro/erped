<?php

use App\Http\Controllers\Api\BackendProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
});

// BFF Proxy — เรียก Backend API ผ่าน server-side (token อยู่ใน session)
Route::middleware(['web', 'auth'])->prefix('proxy')->group(function () {
    Route::get('{endpoint}', [BackendProxyController::class, 'get'])->where('endpoint', '.*');
    Route::post('{endpoint}', [BackendProxyController::class, 'post'])->where('endpoint', '.*');
    Route::put('{endpoint}', [BackendProxyController::class, 'put'])->where('endpoint', '.*');
    Route::delete('{endpoint}', [BackendProxyController::class, 'delete'])->where('endpoint', '.*');
});
