<?php

declare(strict_types=1);

namespace Modules\Store;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Modules\AutoDex\Domain\Models\Car;
use Modules\AutoServe\Domain\Models\Sparepart;
use Modules\Store\Console\Commands\AutoCaptureC2cOrdersCommand;
use Modules\Store\Console\Commands\CancelStaleOrdersCommand;
use Modules\Store\Domain\Models\Order;
use Modules\Store\Domain\Models\StoreItem;

class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
    }
}
