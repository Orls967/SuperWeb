<?php

declare(strict_types=1);

namespace Modules\Resto;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Console\Commands\CloseDayCommand;
use Modules\Resto\Console\Commands\ExpireDisplayTraysCommand;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\Shift;
use Modules\Resto\Domain\Models\TableSession;
use Modules\Shared\Application\MenuRegistry;

class RestoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecipeCostCalculator::class, fn () => new RecipeCostCalculator);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'resto');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        Relation::morphMap([
            'resto_outlet' => Outlet::class,
            'resto_ingredient' => Ingredient::class,
            'resto_menu_item' => MenuItem::class,
            'resto_recipe' => Recipe::class,
            'resto_batch' => ProductionBatch::class,
            'resto_tray' => DisplayTray::class,
            'resto_order' => Order::class,
            'resto_shift' => Shift::class,
            'resto_table' => RestoTable::class,
            'resto_session' => TableSession::class,
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'POS Kasir & Meja',
                route: 'resto.pos.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier'],
                order: 58,
                group: 'Kuliner (RM Sari Ranah)',
                activePattern: 'resto/pos*'
            );

            $registry->addItem(
                label: 'Dapur & Etalase',
                route: 'resto.kitchen.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path></svg>',
                roles: ['admin', 'outlet_manager', 'kitchen'],
                order: 59,
                group: 'Kuliner (RM Sari Ranah)',
                activePattern: 'resto/kitchen*'
            );

            $registry->addItem(
                label: 'Menu Padang',
                route: 'resto.menu.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier', 'kitchen'],
                order: 60,
                group: 'Kuliner (RM Sari Ranah)',
                activePattern: 'resto/menu*'
            );

            $registry->addItem(
                label: 'Bahan Baku & Resep',
                route: 'resto.ingredients.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
                roles: ['admin', 'outlet_manager', 'kitchen'],
                order: 61,
                group: 'Kuliner (RM Sari Ranah)',
                activePattern: 'resto/ingredients*'
            );

            $registry->addItem(
                label: 'Outlet & Dapur Sentral',
                route: 'resto.outlets.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 62,
                group: 'Kuliner (RM Sari Ranah)',
                activePattern: 'resto/outlets*'
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireDisplayTraysCommand::class,
                CloseDayCommand::class,
            ]);
        }
    }
}
