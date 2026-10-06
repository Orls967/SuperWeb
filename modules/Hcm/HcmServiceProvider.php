<?php

declare(strict_types=1);

namespace Modules\Hcm;

use Illuminate\Support\ServiceProvider;
use Modules\Hcm\Application\Services\HcmService;
use Modules\Hcm\Console\Commands\AuditHcmCommand;

class HcmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HcmService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hcm');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditHcmCommand::class,
            ]);
        }
    }
}
