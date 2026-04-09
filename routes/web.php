<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
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

Route::middleware(['auth', 'permission:users.view'])
    ->controller(AppPageController::class)
    ->name('users.')
    ->group(function () {
        Route::get('/users', 'users')->name('index');
    });

Route::middleware(['auth', 'permission:companies.view'])
    ->controller(AppPageController::class)
    ->name('companies.')
    ->group(function () {
        Route::get('/companies', 'companies')->name('index');
    });

Route::middleware(['auth', 'permission:employees.view'])
    ->controller(EmployeeController::class)
    ->name('employees.')
    ->group(function () {
        Route::get('/employees', 'index')->name('index');
    });

Route::middleware(['auth', 'permission:roles.view'])
    ->controller(AppPageController::class)
    ->name('roles.')
    ->group(function () {
        Route::get('/roles', 'roles')->name('index');
    });

Route::middleware(['auth', 'permission:permissions.view'])
    ->controller(AppPageController::class)
    ->name('permissions.')
    ->group(function () {
        Route::get('/permissions', 'permissions')->name('index');
    });

Route::prefix('api')->middleware(['auth'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-logs.view')
        ->name('api.audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit-logs.view')
        ->name('api.audit-logs.show');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('api.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('api.users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('api.users.update');

    Route::get('/companies', [CompanyController::class, 'index'])
        ->middleware('permission:companies.view')
        ->name('api.companies.index');
    Route::post('/companies', [CompanyController::class, 'store'])
        ->middleware('permission:companies.create')
        ->name('api.companies.store');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])
        ->middleware('permission:companies.update')
        ->name('api.companies.update');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
        ->middleware('permission:companies.delete')
        ->name('api.companies.destroy');

    Route::get('/employees', [EmployeeController::class, 'fetch'])
        ->middleware('permission:employees.view')
        ->name('api.employees.fetch');
    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('permission:employees.create')
        ->name('api.employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:employees.update')
        ->name('api.employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:employees.delete')
        ->name('api.employees.destroy');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('api.roles.index');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('api.roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.update')
        ->name('api.roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('api.roles.destroy');

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:permissions.view')
        ->name('api.permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:permissions.create')
        ->name('api.permissions.store');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:permissions.update')
        ->name('api.permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('permission:permissions.delete')
        ->name('api.permissions.destroy');
});

require __DIR__ . '/auth.php';
