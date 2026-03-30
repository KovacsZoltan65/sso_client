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

Route::middleware(['auth', 'permission:companies.view'])->group(function () {
    Route::get('/companies', [AppPageController::class, 'companies'])->name('companies.index');
});

Route::middleware(['auth', 'permission:roles.view'])->group(function () {
    Route::get('/roles', [AppPageController::class, 'roles'])->name('roles.index');
});

Route::middleware(['auth', 'permission:permissions.view'])->group(function () {
    Route::get('/permissions', [AppPageController::class, 'permissions'])->name('permissions.index');
});

Route::prefix('api')->middleware(['auth'])->group(function () {
    Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('api.users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('api.users.show');
    Route::put('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('api.users.update');

    Route::get('/companies', [\App\Http\Controllers\Api\CompanyController::class, 'index'])
        ->middleware('permission:companies.view')
        ->name('api.companies.index');
    Route::post('/companies', [\App\Http\Controllers\Api\CompanyController::class, 'store'])
        ->middleware('permission:companies.create')
        ->name('api.companies.store');
    Route::put('/companies/{company}', [\App\Http\Controllers\Api\CompanyController::class, 'update'])
        ->middleware('permission:companies.update')
        ->name('api.companies.update');
    Route::delete('/companies/{company}', [\App\Http\Controllers\Api\CompanyController::class, 'destroy'])
        ->middleware('permission:companies.delete')
        ->name('api.companies.destroy');
});

require __DIR__.'/auth.php';
