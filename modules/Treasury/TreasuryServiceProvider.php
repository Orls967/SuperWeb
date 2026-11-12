<?php

declare(strict_types=1);

namespace Modules\Treasury;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Treasury\Application\Listeners\TreasuryDomainEventSubscriber;
use Modules\Treasury\Application\Services\BankStatementImportService;
use Modules\Treasury\Application\Services\TreasuryCovenantGovernanceService;
use Modules\Treasury\Application\Services\TreasuryEventPublisher;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Application\Services\TreasuryTaskInboxNotifier;
use Modules\Treasury\Console\Commands\AuditTreasuryCommand;
use Modules\Treasury\Console\Commands\CashPoolSweepCommand;
use Modules\Treasury\Console\Commands\DailyMarkToMarketCommand;
use Modules\Treasury\Console\Commands\ExportTreasuryReportsCommand;
use Modules\Treasury\Console\Commands\GenerateCashForecastCommand;
use Modules\Treasury\Console\Commands\ImportBankStatementCommand;
use Modules\Treasury\Console\Commands\ReconcileBankStatementsCommand;
use Modules\Treasury\Console\Commands\ReverseFxRevaluationCommand;
use Modules\Treasury\Console\Commands\RunFxRevaluationCommand;
use Modules\Treasury\Console\Commands\TestCreditCovenantCommand;
use Modules\Treasury\Contracts\ExchangeRateLookup;
use Modules\Treasury\Contracts\FxMonetaryItemFeed;
use Modules\Treasury\Domain\Events\TreasuryCovenantBreachedEvent;
use Modules\Treasury\Domain\Events\TreasuryHedgeMaturedEvent;
use Modules\Treasury\Domain\Events\TreasuryRevaluationPostedEvent;
use Modules\Treasury\Infrastructure\Api\ExchangeRateResourceProvider;

class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TreasuryService::class);
        $this->app->singleton(TreasuryLedgerPoster::class);
        $this->app->singleton(TreasuryEventPublisher::class);
        $this->app->singleton(TreasuryTaskInboxNotifier::class);
        $this->app->singleton(TreasuryDomainEventSubscriber::class);
        $this->app->singleton(BankStatementImportService::class);
        $this->app->singleton(TreasuryCovenantGovernanceService::class);

        $this->app->bind(ExchangeRateLookup::class, TreasuryService::class);
        $this->app->bind(FxMonetaryItemFeed::class, TreasuryService::class);

        $this->app->tag(TreasuryService::class, 'treasury.fx_monetary_item_feed');
        $this->app->tag(ExchangeRateResourceProvider::class, 'core.api_resource_provider');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (file_exists(__DIR__.'/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        if (is_dir(__DIR__.'/resources/views/treasury')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/treasury', 'treasury');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditTreasuryCommand::class,
                RunFxRevaluationCommand::class,
                ReverseFxRevaluationCommand::class,
                ImportBankStatementCommand::class,
                ReconcileBankStatementsCommand::class,
                DailyMarkToMarketCommand::class,
                TestCreditCovenantCommand::class,
                CashPoolSweepCommand::class,
                GenerateCashForecastCommand::class,
                ExportTreasuryReportsCommand::class,
            ]);
        }

        Event::listen(
            TreasuryRevaluationPostedEvent::class,
            [TreasuryDomainEventSubscriber::class, 'handleRevaluationPosted']
        );
        Event::listen(
            TreasuryCovenantBreachedEvent::class,
            [TreasuryDomainEventSubscriber::class, 'handleCovenantBreached']
        );
        Event::listen(
            TreasuryHedgeMaturedEvent::class,
            [TreasuryDomainEventSubscriber::class, 'handleHedgeMatured']
        );

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Treasury & Valas',
            route: 'treasury.index',
            icon: 'banknotes',
            roles: ['admin', 'auditor', 'treasury'],
            order: 62,
            group: 'Keuangan',
            activePattern: 'treasury*',
        );
    }
}
