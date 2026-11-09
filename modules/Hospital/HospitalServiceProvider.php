<?php

declare(strict_types=1);

namespace Modules\Hospital;

use Illuminate\Support\ServiceProvider;
use Modules\Hospital\Application\Services\HospitalEmrAndBedService;
use Modules\Hospital\Application\Services\HospitalEncounterBillReaderService;
use Modules\Hospital\Application\Services\MedicalEmergencyResponseDispatcherService;
use Modules\Hospital\Console\Commands\AuditHospitalCommand;
use Modules\Hospital\Console\Commands\BedSweepCommand;
use Modules\Hospital\Console\Commands\ClaimAgingReportCommand;
use Modules\Hospital\Console\Commands\ColdChainMonitorCommand;
use Modules\Hospital\Console\Commands\EfficiencyReportCommand;
use Modules\Hospital\Console\Commands\FestronikAuditCommand;
use Modules\Hospital\Console\Commands\PathwayScanCommand;
use Modules\Hospital\Console\Commands\TelemetryScanCommand;
use Modules\Hospital\Contracts\EncounterBillReader;
use Modules\Hospital\Contracts\MedicalEmergencyResponseDispatcher;

class HospitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HospitalEmrAndBedService::class);
        $this->app->bind(EncounterBillReader::class, HospitalEncounterBillReaderService::class);
        $this->app->bind(MedicalEmergencyResponseDispatcher::class, MedicalEmergencyResponseDispatcherService::class);
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
                BedSweepCommand::class,
                PathwayScanCommand::class,
                EfficiencyReportCommand::class,
                TelemetryScanCommand::class,
                ColdChainMonitorCommand::class,
                ClaimAgingReportCommand::class,
                FestronikAuditCommand::class,
            ]);
        }
    }
}
