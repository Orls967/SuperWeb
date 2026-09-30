<?php

declare(strict_types=1);

namespace Modules\Resto;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\Recipe;
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
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

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
    }
}
