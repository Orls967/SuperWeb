<?php

namespace Modules\Venue;

use Illuminate\Support\ServiceProvider;
use Modules\Venue\Application\Services\VenueOperationsService;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VenueOperationsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
