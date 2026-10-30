<?php

declare(strict_types=1);

namespace Modules\Venue;

use Illuminate\Support\ServiceProvider;
use Modules\Party\Contracts\PartyAgeVerificationInterface;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\Console\Commands\AuditVenueCommand;
use Modules\Venue\Console\Commands\CloseEventsCommand;

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
                CloseEventsCommand::class,
            ]);
        }
    }
}
