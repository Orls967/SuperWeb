<?php

declare(strict_types=1);

namespace Modules\Insurance;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Console\Commands\AuditInsuranceCommand;

class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InsurTechClaimsService::class, function ($app) {
            return new InsurTechClaimsService(
                $app->make(SimClockInterface::class),
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'insurance');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditInsuranceCommand::class,
            ]);
        }
    }
}
