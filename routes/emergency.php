<?php

use App\Http\Controllers\Emergency\EmergencyActivationController;
use App\Http\Controllers\Emergency\EmergencyDashboardController;
use App\Http\Controllers\Emergency\EmergencyReadOnlyController;
use App\Http\Controllers\Emergency\EmergencySessionController;
use App\Http\Controllers\Emergency\EmergencyStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('emergency')
    ->middleware(['emergency.enabled'])
    ->group(function (): void {
        Route::get('/', EmergencyStatusController::class)->name('emergency.index');
        Route::get('/status', EmergencyStatusController::class)->name('emergency.status');

        Route::middleware(['guest:emergency'])->group(function (): void {
            Route::get('/login', [EmergencySessionController::class, 'create'])->name('emergency.login');
            Route::post('/login', [EmergencySessionController::class, 'store'])->name('emergency.login.store');
        });

        Route::middleware(['emergency.active', 'auth:emergency'])->group(function (): void {
            Route::post('/logout', [EmergencySessionController::class, 'destroy'])->name('emergency.logout');
            Route::get('/dashboard', EmergencyDashboardController::class)
                ->middleware('emergency.role:viewer,admin')
                ->name('emergency.dashboard');
            Route::get('/audit-logs', [EmergencyReadOnlyController::class, 'auditLogs'])
                ->middleware('emergency.role:viewer,admin')
                ->name('emergency.audit-logs');
            Route::get('/users', [EmergencyReadOnlyController::class, 'users'])
                ->middleware('emergency.role:viewer,admin')
                ->name('emergency.users');
            Route::get('/companies', [EmergencyReadOnlyController::class, 'companies'])
                ->middleware('emergency.role:viewer,admin')
                ->name('emergency.companies');
            Route::post('/deactivate', [EmergencyActivationController::class, 'destroy'])
                ->middleware('emergency.role:admin')
                ->name('emergency.deactivate');
        });
    });
