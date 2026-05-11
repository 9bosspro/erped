<?php

use Engine\Modules\Auth\Http\Controllers\Web\SocialLoginController;
// use Engine\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('auths', AuthController::class)->names('auth');
}); */

// ไปยังหน้า Login ของ Social นั้นๆ
Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');
