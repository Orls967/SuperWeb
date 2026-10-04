<?php

declare(strict_types=1);

namespace Modules\Contract;

use Illuminate\Support\ServiceProvider;
use Modules\Contract\Application\Services\ContractService;
use Modules\Contract\Console\Commands\RemindContractObligationsCommand;
use Modules\Contract\Console\Commands\VerifyContractChainsCommand;
use Modules\Shared\Application\MenuRegistry;

class ContractServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContractService::class);
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
