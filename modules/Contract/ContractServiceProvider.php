<?php

declare(strict_types=1);

namespace Modules\Contract;

use Illuminate\Support\ServiceProvider;
use Modules\Contract\Application\Services\ContractAmendmentService;
use Modules\Contract\Application\Services\ContractFinanceService;
use Modules\Contract\Application\Services\ContractRateResolver;
use Modules\Contract\Application\Services\ContractReportService;
use Modules\Contract\Application\Services\ContractRiskService;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Application\Services\ContractUsageService;
use Modules\Contract\Application\Services\ContractUsageSyncService;
use Modules\Contract\Console\Commands\AuditContractCommand;
use Modules\Contract\Console\Commands\RemindContractObligationsCommand;
use Modules\Contract\Console\Commands\VerifyContractChainsCommand;
use Modules\Logistics\Contracts\RateCardOverrideResolver;
use Modules\Shared\Application\MenuRegistry;

class ContractServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContractService::class);
        $this->app->singleton(ContractFinanceService::class);
        $this->app->singleton(ContractUsageService::class);
        $this->app->singleton(ContractUsageSyncService::class);
        $this->app->singleton(ContractReportService::class);
        $this->app->singleton(ContractRiskService::class);
        $this->app->singleton(ContractAmendmentService::class);

        // 29.6 — rate card kontrak mengalahkan tarif standar (interface contract).
        $this->app->bind(RateCardOverrideResolver::class, ContractRateResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/contract', 'contract');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyContractChainsCommand::class,
                RemindContractObligationsCommand::class,
                AuditContractCommand::class,
            ]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $menu = $this->app->make(MenuRegistry::class);

        $menu->addItem(
            label: 'Kontrak',
            route: 'contract.index',
            icon: 'document-text',
            roles: ['admin', 'contract_manager', 'legal'],
            order: 47,
            group: 'Master Data',
            activePattern: 'contract*',
        );
    }
}
