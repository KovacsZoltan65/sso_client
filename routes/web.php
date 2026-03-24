<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/my-account', [AppPageController::class, 'myAccount'])->name('account.show');
    Route::get('/sso-status', [AppPageController::class, 'ssoStatus'])
        ->middleware('permission:sso-status.view')
        ->name('sso.status');
    Route::get('/audit-logs', [AppPageController::class, 'auditLogs'])
        ->middleware('permission:audit-logs.view')
        ->name('audit-logs.index');
});

Route::middleware(['auth', 'permission:users.view'])->group(function () {
    Route::get('/users', [AppPageController::class, 'users'])->name('users.index');
});

Route::middleware(['auth', 'permission:roles.view'])->group(function () {
    Route::get('/roles', [AppPageController::class, 'roles'])->name('roles.index');
});

Route::middleware(['auth', 'permission:permissions.view'])->group(function () {
    Route::get('/permissions', [AppPageController::class, 'permissions'])->name('permissions.index');
});

require __DIR__.'/auth.php';
