<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance;

use Illuminate\Support\ServiceProvider;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Console\Commands\AuditEnterpriseFinanceCommand;
use Modules\Shared\Application\MenuRegistry;

class EnterpriseFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnterpriseFinanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/enterprise_finance')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/enterprise_finance', 'enterprise_finance');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditEnterpriseFinanceCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Finance Grup & Anggaran',
            route: 'enterprise_finance.index',
            icon: 'calculator',
            roles: ['admin', 'auditor', 'treasury'],
            order: 68,
            group: 'Keuangan',
            activePattern: 'enterprise-finance*',
        );
    }
}
