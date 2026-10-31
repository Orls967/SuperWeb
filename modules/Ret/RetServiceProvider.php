<?php

declare(strict_types=1);

namespace Modules\Ret;

use Illuminate\Support\ServiceProvider;
use Modules\Ret\Console\Commands\RetAuditCommand;
use Modules\Ret\Console\Commands\RetSettleSellersCommand;
use Modules\Ret\Contracts\MapPriceLookupInterface;
use Modules\Ret\Infrastructure\Adapters\DefaultMapPriceLookupAdapter;
use Modules\Shared\Application\MenuRegistry;

class RetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            MapPriceLookupInterface::class,
            DefaultMapPriceLookupAdapter::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'ret');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RetAuditCommand::class,
                RetSettleSellersCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Ritel & E-Commerce',
                route: 'retail.index',
                icon: 'shopping-bag',
                roles: ['retail_ops', 'marketplace_mgr', 'category_mgr', 'last_mile_cs'],
                order: 170,
            );
        }
    }
}
