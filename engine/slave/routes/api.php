<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Slave\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Slave API Routes
|--------------------------------------------------------------------------
|
| Routes สำหรับรับ request จาก Master Server
| ทุก route ใช้ middleware 'api' เพื่อรับประกัน stateless + JSON
|
*/

Route::prefix('slave')
    ->name('slave.')
    ->middleware(['api'])
    ->group(function (): void {

        // Health check — Master ใช้ตรวจสอบว่า Slave ยังทำงานอยู่
        Route::get('/health', static function (): \Illuminate\Http\JsonResponse {
            return response()->json([
                'status'  => 'ok',
                'version' => slave_version(),
                'time'    => now()->toIso8601String(),
            ]);
        })->name('health');

        // Webhook receiver — รับ event notifications จาก Master
        Route::post('/webhook', WebhookController::class)->name('webhook');
    });
