<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\LocalFallbackSessionController;
use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::get('local-login', [LocalFallbackSessionController::class, 'create'])
        ->middleware('local_fallback_page')
        ->name('local-login');

    Route::post('local-login', [LocalFallbackSessionController::class, 'store'])
        ->middleware('local_fallback_submit')
        ->name('local-login.store');

    Route::get('auth/sso/redirect', [SsoAuthController::class, 'redirect'])
        ->name('auth.sso.redirect');

    Route::get('auth/sso/callback', [SsoAuthController::class, 'callback'])
        ->name('auth.sso.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('auth/logout', [SsoAuthController::class, 'logout'])
        ->name('logout');
});
