<?php

declare(strict_types=1);

namespace Modules\Intercompany;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Intercompany\Application\Services\ConsolidationEliminationRollupOrchestrator;
use Modules\Intercompany\Application\Services\DefaultSubledgerTotalProvider;
use Modules\Intercompany\Application\Services\GroupEntityRegistry;
use Modules\Intercompany\Application\Services\GroupTransferPricingAuditPackExporter;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Application\Services\IntercompanyMismatchSweepService;
use Modules\Intercompany\Application\Services\IntercompanyPortService;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Console\Commands\AccrueIntercompanyInterestCommand;
use Modules\Intercompany\Console\Commands\AllocateCostsCommand;
use Modules\Intercompany\Console\Commands\AuditIntercompanyCommand;
use Modules\Intercompany\Console\Commands\EliminatePeriodCommand;
use Modules\Intercompany\Console\Commands\ExportIntercompanyReportsCommand;
use Modules\Intercompany\Console\Commands\ExportTpAuditPackCommand;
use Modules\Intercompany\Console\Commands\GenerateTpDocumentCommand;
use Modules\Intercompany\Console\Commands\LockConsolidationCommand;
use Modules\Intercompany\Console\Commands\ReconcileIntercompanyMatrixCommand;
use Modules\Intercompany\Console\Commands\SweepCashPoolCommand;
use Modules\Intercompany\Console\Commands\SweepIntercompanyMismatchCommand;
use Modules\Intercompany\Console\Commands\TpTrueUpCommand;
use Modules\Intercompany\Console\Commands\TranslateSubsidiaryFinancialsCommand;
use Modules\Intercompany\Contracts\IntercompanyBillingPort;
use Modules\Intercompany\Contracts\IntercompanyPortInterface;
use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Intercompany\Domain\Algorithms\BilateralNettedSettlementMatrixCalculator;
use Modules\Intercompany\Domain\Algorithms\ConsolidationRollupTreeOptimizer;
use Modules\Intercompany\Domain\Algorithms\MultivariateBenchmarkComparator;
use Modules\Intercompany\Domain\Algorithms\NciDividendCascadeCalculator;
use Modules\Intercompany\Domain\Services\ConsolidationAdjustmentJournalGenerator;
use Modules\Intercompany\Domain\Services\IntercompanyAssetValuationDisputeResolver;
use Modules\Intercompany\Domain\Services\IntercompanyCashPoolSweeper;
use Modules\Intercompany\Domain\Services\TransferPricingLocalFileGenerator;
use Modules\Intercompany\Ledger\IntercompanyTransactionTypes;
use Modules\Shared\Application\MenuRegistry;

class IntercompanyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntercompanyService::class);
        $this->app->singleton(IntercompanyLedgerPoster::class);
        $this->app->singleton(GroupEntityRegistry::class);
        $this->app->singleton(IntercompanyMismatchSweepService::class);
        $this->app->singleton(MultivariateBenchmarkComparator::class);
        $this->app->singleton(BilateralNettedSettlementMatrixCalculator::class);
        $this->app->singleton(ConsolidationRollupTreeOptimizer::class);
        $this->app->singleton(NciDividendCascadeCalculator::class);
        $this->app->singleton(ConsolidationAdjustmentJournalGenerator::class);
        $this->app->singleton(TransferPricingLocalFileGenerator::class);
        $this->app->singleton(IntercompanyCashPoolSweeper::class);
        $this->app->singleton(IntercompanyAssetValuationDisputeResolver::class);
        $this->app->singleton(GroupTransferPricingAuditPackExporter::class);
        $this->app->singleton(ConsolidationEliminationRollupOrchestrator::class);
        $this->app->singleton(
            SubledgerTotalProvider::class,
            DefaultSubledgerTotalProvider::class
        );
        $this->app->singleton(
            IntercompanyPortInterface::class,
            IntercompanyPortService::class
        );
        $this->app->singleton(
            IntercompanyBillingPort::class,
            IntercompanyPortService::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        TransactionTypeRegistry::register(IntercompanyTransactionTypes::class);

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (file_exists(__DIR__.'/routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/api.php');
        }

        if (is_dir(__DIR__.'/resources/views/intercompany')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/intercompany', 'intercompany');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditIntercompanyCommand::class,
                AccrueIntercompanyInterestCommand::class,
                SweepIntercompanyMismatchCommand::class,
                EliminatePeriodCommand::class,
                AllocateCostsCommand::class,
                TpTrueUpCommand::class,
                TranslateSubsidiaryFinancialsCommand::class,
                GenerateTpDocumentCommand::class,
                ExportIntercompanyReportsCommand::class,
                ReconcileIntercompanyMatrixCommand::class,
                ExportTpAuditPackCommand::class,
                SweepCashPoolCommand::class,
                LockConsolidationCommand::class,
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
            label: 'Konsolidasi Grup & IC',
            route: 'intercompany.index',
            icon: 'library',
            roles: ['admin', 'auditor', 'treasury'],
            order: 66,
            group: 'Keuangan',
            activePattern: 'intercompany*',
        );
    }
}
