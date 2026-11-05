<?php

declare(strict_types=1);

namespace Modules\Rwa;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Rwa\Application\Services\RwaCatBondTokenRegistryService;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Application\Services\RwaVotingWeightReaderService;
use Modules\Rwa\Console\Commands\AuditRwaCommand;
use Modules\Rwa\Contracts\CatBondTokenRegistry;
use Modules\Rwa\Contracts\VotingWeightReader;

class RwaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VotingWeightReader::class, RwaVotingWeightReaderService::class);
        $this->app->bind(CatBondTokenRegistry::class, RwaCatBondTokenRegistryService::class);

        $this->app->singleton(RwaTokenService::class, function ($app) {
            return new RwaTokenService(
                $app->make(SimClockInterface::class),
                $app->make(LedgerService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'rwa');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditRwaCommand::class,
            ]);
        }
    }
}
