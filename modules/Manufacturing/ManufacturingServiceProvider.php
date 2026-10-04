<?php

declare(strict_types=1);

namespace Modules\Manufacturing;

use Illuminate\Support\ServiceProvider;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Shared\Application\MenuRegistry;

class ManufacturingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ManufacturingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/manufacturing')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/manufacturing', 'manufacturing');
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Pabrik',
            route: 'manufacturing.index',
            icon: 'cog',
            roles: ['admin', 'planner', 'operator', 'qc_inspector'],
            order: 50,
            group: 'Produksi',
            activePattern: 'manufacturing*',
        );
    }
}
