<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Employee::class => EmployeePolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}