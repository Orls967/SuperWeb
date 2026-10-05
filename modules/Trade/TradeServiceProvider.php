<?php

declare(strict_types=1);

namespace Modules\Trade;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Trade\Application\Services\TradeService;
use Modules\Trade\Console\Commands\AuditTradeCommand;

class TradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TradeService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/trade')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/trade', 'trade');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditTradeCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Ekspor - Impor',
            route: 'trade.index',
            icon: 'globe',
            roles: ['admin', 'auditor', 'procurement', 'logistics_admin'],
            order: 63,
            group: 'Logistik',
            activePattern: 'trade*',
        );
    }
}
