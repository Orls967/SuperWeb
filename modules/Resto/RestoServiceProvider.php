<?php

declare(strict_types=1);

namespace Modules\Resto;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\Resto\Application\Services\IngredientReferenceCostUpdater;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Application\Services\RestoTenantSalesProvider;
use Modules\Resto\Console\Commands\CloseDayCommand;
use Modules\Resto\Console\Commands\ExpireDisplayTraysCommand;
use Modules\Resto\Console\Commands\MonitorStockLevelsCommand;
use Modules\Resto\Console\Commands\PostFranchiseRoyaltyCommand;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Resto\Domain\Models\Delivery;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\OutletContract;
use Modules\Resto\Domain\Models\ProductionBatch;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\Recipe;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\RoyaltyPosting;
use Modules\Resto\Domain\Models\Shift;
use Modules\Resto\Domain\Models\StockCount;
use Modules\Resto\Domain\Models\StockTransfer;
use Modules\Resto\Domain\Models\TableSession;
use Modules\Shared\Application\MenuRegistry;
use Modules\Supplier\Contracts\ReferenceCostUpdater;

class RestoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 32.8 — kontrak harga pemasok → MAC referensi (tanpa import domain lintas modul).
        $this->app->bind(
            ReferenceCostUpdater::class,
            IngredientReferenceCostUpdater::class
        );

        $this->app->singleton(RecipeCostCalculator::class, fn () => new RecipeCostCalculator);
        $this->app->singleton(RestoTenantSalesProvider::class);
        $this->app->tag(RestoTenantSalesProvider::class, 'mall.tenant_sales_provider');
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
            'resto_purchase_order' => PurchaseOrder::class,
            'resto_transfer' => StockTransfer::class,
            'resto_stock_count' => StockCount::class,
            'resto_delivery' => Delivery::class,
            'resto_catering_order' => CateringOrder::class,
            'resto_contract' => OutletContract::class,
            'resto_royalty_posting' => RoyaltyPosting::class,
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'POS Kasir & Meja',
                route: 'resto.pos.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier'],
                order: 58,
                group: 'Kuliner',
                activePattern: 'resto/pos*'
            );

            $registry->addItem(
                label: 'Dapur & Etalase',
                route: 'resto.kitchen.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path></svg>',
                roles: ['admin', 'outlet_manager', 'kitchen'],
                order: 59,
                group: 'Kuliner',
                activePattern: 'resto/kitchen*'
            );

            $registry->addItem(
                label: 'Menu Padang',
                route: 'resto.menu.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier', 'kitchen'],
                order: 60,
                group: 'Kuliner',
                activePattern: 'resto/menu*'
            );

            $registry->addItem(
                label: 'Bahan Baku & Resep',
                route: 'resto.ingredients.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
                roles: ['admin', 'outlet_manager', 'kitchen'],
                order: 61,
                group: 'Kuliner',
                activePattern: 'resto/ingredients*'
            );

            $registry->addItem(
                label: 'Outlet & Dapur Sentral',
                route: 'resto.outlets.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 62,
                group: 'Kuliner',
                activePattern: 'resto/outlets*'
            );

            $registry->addItem(
                label: 'Rantai Pasok & PO',
                route: 'resto.purchases.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 63,
                group: 'Kuliner',
                activePattern: 'resto/purchases*'
            );

            $registry->addItem(
                label: 'Transfer Antar Outlet',
                route: 'resto.transfers.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>',
                roles: ['admin', 'outlet_manager', 'kitchen'],
                order: 64,
                group: 'Kuliner',
                activePattern: 'resto/transfers*'
            );

            $registry->addItem(
                label: 'Stock Opname',
                route: 'resto.stock-counts.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 65,
                group: 'Kuliner',
                activePattern: 'resto/stock-counts*'
            );

            $registry->addItem(
                label: 'Delivery & Bungkus',
                route: 'resto.deliveries.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier'],
                order: 66,
                group: 'Kuliner',
                activePattern: 'resto/deliveries*'
            );

            $registry->addItem(
                label: 'Pesanan Katering',
                route: 'resto.catering.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"></path></svg>',
                roles: ['admin', 'outlet_manager', 'cashier'],
                order: 67,
                group: 'Kuliner',
                activePattern: 'resto/catering*'
            );

            $registry->addItem(
                label: 'Waralaba & Royalti',
                route: 'resto.franchise.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 68,
                group: 'Kuliner',
                activePattern: 'resto/franchise*'
            );

            $registry->addItem(
                label: 'Analitik & Menu BCG',
                route: 'resto.analytics.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
                roles: ['admin', 'outlet_manager'],
                order: 69,
                group: 'Kuliner',
                activePattern: 'resto/analytics*'
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireDisplayTraysCommand::class,
                CloseDayCommand::class,
                MonitorStockLevelsCommand::class,
                PostFranchiseRoyaltyCommand::class,
            ]);
        }
    }
}
