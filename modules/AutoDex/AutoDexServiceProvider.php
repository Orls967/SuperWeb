<?php

declare(strict_types=1);

namespace Modules\AutoDex;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;

class AutoDexServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'dex');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $registry = $this->app->make(MenuRegistry::class);
        $registry->addItem(
            label: 'Katalog Mobil',
            route: 'autodex.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
            roles: [],
            order: 30,
            group: 'AutoDex',
            activePattern: 'autodex.index',
        );

        $registry->addItem(
            label: 'My Garage & Wishlist',
            route: 'autodex.garage.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            roles: [],
            order: 31,
            group: 'AutoDex',
            activePattern: 'autodex.garage.*',
        );
    }
}
