<?php

declare(strict_types=1);

namespace Modules\Procurement;

use Illuminate\Support\ServiceProvider;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Shared\Application\MenuRegistry;

class ProcurementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProcurementService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/procurement', 'procurement');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Procurement',
            route: 'procurement.dashboard',
            icon: 'shopping-cart',
            roles: ['admin', 'procurement'],
            order: 47,
            group: 'Supply Chain',
            activePattern: 'procurement*',
        );
    }
}
