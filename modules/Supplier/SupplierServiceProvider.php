<?php

declare(strict_types=1);

namespace Modules\Supplier;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Console\Commands\RemindSupplierCertificationsCommand;
use Modules\Supplier\Console\Commands\ScanSupplierRisksCommand;

class SupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/supplier', 'supplier');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanSupplierRisksCommand::class,
                RemindSupplierCertificationsCommand::class,
            ]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Pemasok',
            route: 'supplier.index',
            icon: 'truck',
            roles: ['admin', 'procurement', 'supplier', 'party_manager'],
            order: 46,
            group: 'Master Data',
            activePattern: 'suppliers*',
        );
    }
}
