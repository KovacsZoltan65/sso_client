<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
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

// Users routes
Route::middleware(['auth', 'permission:users.view'])
    ->controller(AppPageController::class)
    ->name('users.')->group(function () {
        Route::get('/users', 'users')->name('index');
    }
);

// Companies routes
Route::middleware(['auth', 'permission:companies.view'])
    ->controller(AppPageController::class)->name('companies.')
    ->group(function () {
        Route::get('/companies', 'companies')->name('index');
    }
);

// Employees routes
Route::middleware(['auth', 'permission:employees.view'])
    ->controller(EmployeeController::class)->name('employees.')
    ->group(function() {
        Route::get('/employees', 'index')->name('index');
    }
);

// Roles routes
Route::middleware(['auth', 'permission:roles.view'])
    ->controller(AppPageController::class)->name('roles.')
    ->group(function () {
        Route::get('/roles', 'roles')->name('index');
    }
);

// Permissions routes
Route::middleware(['auth', 'permission:permissions.view'])
    ->controller(AppPageController::class)->name('permissions.')
    ->group(function () {
        Route::get('/permissions', 'permissions')->name('index');
    }
);

// API routes
Route::prefix('api')->middleware(['auth'])->group(function () {
    
    // Users routes
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('api.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('api.users.show');   
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('api.users.update');

    // Companies routes
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
    
    // Employees routes
    //Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view')->name('api.employees.index');
    
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
    
});

require __DIR__.'/auth.php';
