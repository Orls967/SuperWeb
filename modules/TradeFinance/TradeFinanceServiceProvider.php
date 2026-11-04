<?php

declare(strict_types=1);

namespace Modules\TradeFinance;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\TradeFinance\Application\Services\Clearing\CbamDeclarationService;
use Modules\TradeFinance\Application\Services\Clearing\ClearingEscrowService;
use Modules\TradeFinance\Application\Services\CrossBorder\CrossBorderClearingService;
use Modules\TradeFinance\Application\Services\TradeFinanceService;
use Modules\TradeFinance\Console\Commands\AuditClearingCommand;
use Modules\TradeFinance\Console\Commands\AuditTradeFinanceCommand;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;
use Modules\TradeFinance\Http\Policies\ClearingEscrowPolicy;

class TradeFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TradeFinanceService::class);
        $this->app->singleton(CrossBorderClearingService::class);
        $this->app->singleton(ClearingEscrowService::class);
        $this->app->singleton(CbamDeclarationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        Gate::policy(ClearingEscrow::class, ClearingEscrowPolicy::class);

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/trade_finance')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/trade_finance', 'trade_finance');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditTradeFinanceCommand::class,
                AuditClearingCommand::class,
            ]);
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

        $menu = $this->app->make(MenuRegistry::class);
        $menu->addItem(
            label: 'Clearing USDT',
            route: 'trade_finance.clearing.index',
            icon: 'document-text',
            roles: ['admin', 'tf_officer', 'importer', 'exporter'],
            order: 65,
            group: 'Keuangan',
            activePattern: 'trade-finance/clearing*',
        );
        $menu->addItem(
            label: 'Sertifikat CBAM',
            route: 'trade.cbam.index',
            icon: 'document-text',
            roles: ['admin', 'compliance_officer'],
            order: 66,
            group: 'Keuangan',
            activePattern: 'trade/cbam*',
        );
    }
}
