<?php

declare(strict_types=1);

namespace Modules\Ret;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;

class RetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings if needed
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

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
