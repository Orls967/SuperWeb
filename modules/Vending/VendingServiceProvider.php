<?php

namespace Modules\Vending;

use Illuminate\Support\ServiceProvider;
use Modules\Vending\Application\Services\SmartVendingService;

class VendingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmartVendingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
