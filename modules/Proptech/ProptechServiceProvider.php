<?php

declare(strict_types=1);

namespace Modules\Proptech;

use Illuminate\Support\ServiceProvider;
use Modules\Proptech\Application\Services\BimTwinService;
use Modules\Proptech\Application\Services\FlexSpaceService;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Console\Commands\AuditBimCommand;
use Modules\Proptech\Console\Commands\AuditFlexCommand;
use Modules\Proptech\Console\Commands\AuditProptechCommand;
use Modules\Proptech\Console\Commands\AuditSmartBuildingCommand;

class ProptechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProptechService::class);
        $this->app->singleton(BimTwinService::class);
        $this->app->singleton(FlexSpaceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'proptech');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditProptechCommand::class,
                AuditSmartBuildingCommand::class,
                AuditBimCommand::class,
                AuditFlexCommand::class,
            ]);
        }
    }
}
