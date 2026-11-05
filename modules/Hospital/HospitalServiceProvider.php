<?php

declare(strict_types=1);

namespace Modules\Hospital;

use Illuminate\Support\ServiceProvider;
use Modules\Hospital\Application\Services\HospitalEmrAndBedService;
use Modules\Hospital\Application\Services\HospitalEncounterBillReaderService;
use Modules\Hospital\Console\Commands\AuditHospitalCommand;
use Modules\Hospital\Contracts\EncounterBillReader;

class HospitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HospitalEmrAndBedService::class);
        $this->app->bind(EncounterBillReader::class, HospitalEncounterBillReaderService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            gc_collect_cycles();
        }

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hospital');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditHospitalCommand::class,
            ]);
        }
    }
}
