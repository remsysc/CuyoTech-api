<?php

namespace App\Providers;

use App\Policies\AdminPolicy;
use App\Policies\CashierPolicy;
use App\Policies\DepartmentStaffPolicy;
use App\Policies\RegistrarPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('student', [StudentPolicy::class, 'before']);
        Gate::define('registrar', [RegistrarPolicy::class, 'before']);
        Gate::define('cashier', [CashierPolicy::class, 'before']);
        Gate::define('department_staff', [DepartmentStaffPolicy::class, 'before']);
        Gate::define('admin', [AdminPolicy::class, 'before']);
    }
}
