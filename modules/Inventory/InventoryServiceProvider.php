<?php

declare(strict_types=1);

namespace Modules\Inventory;

use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Application\Services\InventoryService;
use Modules\Inventory\Contracts\InventoryBalanceProvider;
use Modules\Inventory\Contracts\InventoryService as InventoryServiceContract;
use Modules\Inventory\Infrastructure\Adapters\InventoryBalanceProviderAdapter;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryServiceContract::class, InventoryService::class);
        $this->app->bind(InventoryBalanceProvider::class, InventoryBalanceProviderAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'inventory');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }
    }
}
