<?php

declare(strict_types=1);

namespace Modules\Telematics;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Telematics\Application\Services\TelematicsIngestService;

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
    }
}
