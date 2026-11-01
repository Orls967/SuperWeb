<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Mobility\Ev\Application\Services\BatteryPassportService;
use Modules\Mobility\Ev\Application\Services\EvChargingService;
use Modules\Mobility\Ev\Console\Commands\AuditEvCommand;
use Modules\Mobility\Ev\Ledger\EvTransactionTypes;

class EvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EvChargingService::class, function ($app) {
            return new EvChargingService(
                $app->make(SimClockInterface::class),
                $app->make(DigitalTwinInterface::class),
                $app->make(LedgerService::class),
                $app->make(BatteryPassportService::class)
            );
        });
    }

    public function boot(): void
    {
        TransactionTypeRegistry::register(EvTransactionTypes::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'ev');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditEvCommand::class,
            ]);
        }
    }
}
