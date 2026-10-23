<?php

declare(strict_types=1);

namespace Modules\CloudKitchen;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\CloudKitchen\Application\Services\CloudKitchenService;
use Modules\CloudKitchen\Console\Commands\AuditCloudKitchenCommand;
use Modules\Core\Contracts\SimClockInterface;

class CloudKitchenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CloudKitchenService::class, function ($app) {
            return new CloudKitchenService(
                $app->make(SimClockInterface::class),
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'cloud_kitchen');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCloudKitchenCommand::class,
            ]);
        }
    }
}
