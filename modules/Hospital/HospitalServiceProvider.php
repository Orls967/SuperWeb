<?php

namespace Modules\Hospital;

use Illuminate\Support\ServiceProvider;
use Modules\Hospital\Application\Services\HospitalEmrAndBedService;

class HospitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HospitalEmrAndBedService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
