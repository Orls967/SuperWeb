<?php

declare(strict_types=1);

namespace Modules\Telematics;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Telematics\Application\Services\TelematicsIngestService;
use Modules\Telematics\Console\Commands\AuditTelematicsCommand;

class TelematicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelematicsIngestService::class, function ($app) {
            return new TelematicsIngestService(
                $app->make(SimClockInterface::class),
                $app->make(EventSpineInterface::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'telematics');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditTelematicsCommand::class,
            ]);
        }
    }
}
