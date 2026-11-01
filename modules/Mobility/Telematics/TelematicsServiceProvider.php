<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Mobility\Telematics\Application\Services\TelematicsIngestService;
use Modules\Mobility\Telematics\Application\Services\VehicleDispatchEligibilityService;
use Modules\Mobility\Telematics\Console\Commands\AuditTelematicsCommand;
use Modules\Mobility\Telematics\Contracts\VehicleDispatchEligibilityContract;

class TelematicsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            VehicleDispatchEligibilityContract::class,
            VehicleDispatchEligibilityService::class
        );

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
