<?php

declare(strict_types=1);

namespace Modules\Venue;

use Illuminate\Support\ServiceProvider;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\Console\Commands\AuditVenueCommand;
use Modules\Venue\Console\Commands\AuditVenueCrowdCommand;
use Modules\Venue\Console\Commands\AuditVenueEconomyCommand;
use Modules\Venue\Console\Commands\AuditVenueSecondaryCommand;
use Modules\Venue\Console\Commands\CheckWeatherConditionsCommand;
use Modules\Venue\Console\Commands\CloseEventCommand;
use Modules\Venue\Console\Commands\CloseEventsCommand;
use Modules\Venue\Console\Commands\ReleaseExpiredHoldsCommand;
use Modules\Venue\Console\Commands\ScanPermitExpiryCommand;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VenueOperationsService::class);

        if (! $this->app->bound(PartyAgeVerificationInterface::class)) {
            $this->app->bind(PartyAgeVerificationInterface::class, function () {
                return new class implements PartyAgeVerificationInterface
                {
                    public function getVerifiedAge(int $partyOrUserId): ?int
                    {
                        return null;
                    }
                };
            });
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'venue');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditVenueCommand::class,
                AuditVenueEconomyCommand::class,
                AuditVenueSecondaryCommand::class,
                AuditVenueCrowdCommand::class,
                CloseEventsCommand::class,
                CloseEventCommand::class,
                ScanPermitExpiryCommand::class,
                CheckWeatherConditionsCommand::class,
                ReleaseExpiredHoldsCommand::class,
            ]);
        }
    }
}
