<?php

declare(strict_types=1);

namespace Modules\TradeFinance;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\TradeFinance\Application\Services\TradeFinanceService;
use Modules\TradeFinance\Console\Commands\AuditTradeFinanceCommand;

class TradeFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TradeFinanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/trade_finance')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/trade_finance', 'trade_finance');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditTradeFinanceCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Trade Finance',
            route: 'trade_finance.index',
            icon: 'document-text',
            roles: ['admin', 'auditor', 'treasury', 'procurement'],
            order: 64,
            group: 'Keuangan',
            activePattern: 'trade-finance*',
        );
    }
}
