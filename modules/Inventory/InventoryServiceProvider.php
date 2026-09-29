<?php

declare(strict_types=1);

namespace Modules\Inventory;

use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Application\Services\InventoryService;
use Modules\Inventory\Contracts\InventoryService as InventoryServiceContract;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryServiceContract::class, InventoryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
