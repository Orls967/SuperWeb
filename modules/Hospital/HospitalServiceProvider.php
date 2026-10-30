<?php

declare(strict_types=1);

namespace Modules\Hospital;

use Illuminate\Support\ServiceProvider;
use Modules\Hospital\Application\Services\HospitalEmrAndBedService;
use Modules\Hospital\Console\Commands\AuditHospitalCommand;
use Modules\Hospital\Console\Commands\AuditOutpatientCommand;

class HospitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HospitalEmrAndBedService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hospital');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditHospitalCommand::class,
                AuditOutpatientCommand::class,
            ]);
        }
    }
}
