<?php

declare(strict_types=1);

namespace Modules\Hcm;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Console\Commands\AuditHcmCommand;
use Modules\Hcm\Console\Commands\EncryptLegacyEmployeeNikCommand;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Hcm\Domain\Models\Payroll;
use Modules\Hcm\Domain\Policies\EmployeePolicy;
use Modules\Hcm\Domain\Policies\PayrollPolicy;
use Modules\Hcm\Domain\Policies\SalaryPolicy;

class HcmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HcmService::class);
    }

    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Payroll::class, PayrollPolicy::class);
        Gate::define('view-any-salary', [SalaryPolicy::class, 'viewAny']);
        Gate::define('view-salary', [SalaryPolicy::class, 'view']);
        Gate::define('view-payroll', [SalaryPolicy::class, 'viewPayroll']);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hcm');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditHcmCommand::class,
                EncryptLegacyEmployeeNikCommand::class,
            ]);
        }
    }
}
