<?php

declare(strict_types=1);

namespace Modules\Vending;

use Illuminate\Support\ServiceProvider;
use Modules\Vending\Application\Services\SmartVendingService;
use Modules\Vending\Console\Commands\AuditSmartVendingCommand;

class VendingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmartVendingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'vending');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditSmartVendingCommand::class,
            ]);
        }
    }
}
