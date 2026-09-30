<?php

declare(strict_types=1);

namespace Modules\Crypto;

use Illuminate\Support\ServiceProvider;
use Modules\Crypto\Application\Services\PriceEngineService;
use Modules\Crypto\Console\Commands\CryptoTickCommand;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Shared\Application\MenuRegistry;

class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PriceFeed::class, PriceEngineService::class);
        $this->app->singleton(PriceEngineService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'crypto');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CryptoTickCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'Pasar Kripto',
                route: 'crypto.market.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>',
                roles: [],
                order: 20,
                group: 'Keuangan',
                activePattern: 'crypto/market*',
            );

            $registry->addItem(
                label: 'Portofolio Kripto',
                route: 'crypto.portfolio.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                roles: ['customer', 'admin'],
                order: 21,
                group: 'Keuangan',
                activePattern: 'crypto/portfolio*',
            );
        }
    }
}
