<?php

declare(strict_types=1);

namespace Modules\Party;

use Illuminate\Support\ServiceProvider;
use Modules\Party\Application\Services\CreditScoringService;
use Modules\Party\Application\Services\PartyService;
use Modules\Party\Application\Services\SanctionScreeningService;
use Modules\Party\Console\Commands\BackfillPartyLinksCommand;
use Modules\Party\Console\Commands\RemindExpiringDocumentsCommand;
use Modules\Shared\Application\MenuRegistry;

class PartyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PartyService::class);
        $this->app->singleton(SanctionScreeningService::class);
        $this->app->singleton(CreditScoringService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/party', 'party');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillPartyLinksCommand::class,
                RemindExpiringDocumentsCommand::class,
            ]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $menu = $this->app->make(MenuRegistry::class);

        $menu->addItem(
            label: 'Parties & Legal',
            route: 'party.index',
            icon: 'building-office',
            roles: ['admin', 'logistics_admin'],
            order: 45,
            group: 'Master Data',
            activePattern: 'party*',
        );
    }
}
