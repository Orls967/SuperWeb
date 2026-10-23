<?php

declare(strict_types=1);

namespace Modules\Store;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\AutoDex\Contracts\Car;
use Modules\AutoServe\Contracts\Sparepart;
use Modules\Core\Contracts\StoreDashboardPort;
use Modules\Shared\Application\MenuRegistry;
use Modules\Shared\Application\SearchRegistry;
use Modules\Store\Application\Services\StoreDashboardAdapter;
use Modules\Store\Application\Services\StoreOrderReaderService;
use Modules\Store\Application\Services\StoreProductStockAdapter;
use Modules\Store\Application\Services\StoreSearchProvider;
use Modules\Store\Console\Commands\AutoCaptureC2cOrdersCommand;
use Modules\Store\Console\Commands\CancelStaleOrdersCommand;
use Modules\Store\Contracts\StoreOrderReaderInterface;
use Modules\Store\Contracts\StoreProductStockPort;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\StoreItem;

class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StoreDashboardPort::class,
            StoreDashboardAdapter::class
        );
        $this->app->bind(
            StoreProductStockPort::class,
            StoreProductStockAdapter::class
        );
        $this->app->bind(
            StoreOrderReaderInterface::class,
            StoreOrderReaderService::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'store');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CancelStaleOrdersCommand::class,
                AutoCaptureC2cOrdersCommand::class,
            ]);
        }

        Relation::morphMap([
            'serve_sparepart' => Sparepart::class,
            'dex_car' => Car::class,
            'store_item' => StoreItem::class,
            'store_order' => Order::class,
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'Katalog Toko',
                route: 'store.catalog.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>',
                roles: [],
                order: 40,
                group: 'Otomotif',
                activePattern: 'store/catalog*',
            );

            $registry->addItem(
                label: 'Mobil Bekas (C2C)',
                route: 'store.c2c.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
                roles: [],
                order: 41,
                group: 'Otomotif',
                activePattern: 'store/c2c*',
            );

            $registry->addItem(
                label: 'Pesanan Saya',
                route: 'store.orders.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
                roles: [],
                order: 42,
                group: 'Otomotif',
                activePattern: 'store/orders*',
            );
        }

        if ($this->app->bound(SearchRegistry::class)) {
            $this->app->make(SearchRegistry::class)
                ->registerProvider(new StoreSearchProvider);
        }
    }
}
