<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Application\Services\AuditDataPackGeneratorService;
use Modules\EnterpriseFinance\Application\Services\BudgetCommitmentService;
use Modules\EnterpriseFinance\Application\Services\ComplianceEscalationService;
use Modules\EnterpriseFinance\Application\Services\CorporateTaxReconciliationService;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceLedgerPoster;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Application\Services\FinancialPeriodClosingService;
use Modules\EnterpriseFinance\Application\Services\GroupFinanceHealthService;
use Modules\EnterpriseFinance\Application\Services\InternalControlTestingService;
use Modules\EnterpriseFinance\Application\Services\SodGovernanceService;
use Modules\EnterpriseFinance\Console\Commands\AggregateTrialBalanceCommand;
use Modules\EnterpriseFinance\Console\Commands\AuditEnterpriseFinanceCommand;
use Modules\EnterpriseFinance\Console\Commands\AuditSodConflictsCommand;
use Modules\EnterpriseFinance\Console\Commands\BudgetSyncCommand;
use Modules\EnterpriseFinance\Console\Commands\CalculateWorkingCapitalCommand;
use Modules\EnterpriseFinance\Console\Commands\CheckComplianceDeadlinesCommand;
use Modules\EnterpriseFinance\Console\Commands\CloseChecklistCommand;
use Modules\EnterpriseFinance\Console\Commands\ComplianceEscalateCommand;
use Modules\EnterpriseFinance\Console\Commands\ControlTestsCommand;
use Modules\EnterpriseFinance\Console\Commands\DataPackCommand;
use Modules\EnterpriseFinance\Console\Commands\EvaluateCovenantsCommand;
use Modules\EnterpriseFinance\Console\Commands\ExportEnterpriseReportsCommand;
use Modules\EnterpriseFinance\Console\Commands\FreezeOverdueBudgetsCommand;
use Modules\EnterpriseFinance\Console\Commands\ReconcileTaxPeriodCommand;
use Modules\EnterpriseFinance\Console\Commands\SodScanCommand;
use Modules\EnterpriseFinance\Console\Commands\TaxReconCommand;
use Modules\EnterpriseFinance\Contracts\BudgetCommitmentPort;
use Modules\EnterpriseFinance\Contracts\ComplianceDeadlineSource;
use Modules\EnterpriseFinance\Domain\StateMachines\EnterpriseBudgetCycleStateMachine;
use Modules\EnterpriseFinance\Infrastructure\Adapters\ApSubledgerTotalAdapter;
use Modules\EnterpriseFinance\Infrastructure\Adapters\ArSubledgerTotalAdapter;
use Modules\EnterpriseFinance\Infrastructure\Adapters\ContractComplianceDeadlineSource;
use Modules\EnterpriseFinance\Infrastructure\Adapters\FixedAssetSubledgerTotalAdapter;
use Modules\EnterpriseFinance\Infrastructure\Adapters\InventorySubledgerTotalAdapter;
use Modules\EnterpriseFinance\Infrastructure\Adapters\RegulatoryLicenseComplianceDeadlineSource;
use Modules\EnterpriseFinance\Infrastructure\Adapters\TaxComplianceDeadlineSource;
use Modules\Intercompany\Contracts\SubledgerTotalProvider;
use Modules\Shared\Application\MenuRegistry;
use Psr\Clock\ClockInterface;

class EnterpriseFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnterpriseFinanceService::class);
        $this->app->singleton(EnterpriseBudgetCycleStateMachine::class);
        $this->app->singleton(EnterpriseFinanceLedgerPoster::class);

        // Bind BudgetCommitmentPort
        $this->app->singleton(BudgetCommitmentPort::class, function ($app) {
            return new BudgetCommitmentService(
                outboxBus: $app->bound(OutboxBusInterface::class) ? $app->make(OutboxBusInterface::class) : null,
                clock: $app->bound(ClockInterface::class) ? $app->make(ClockInterface::class) : null
            );
        });
        $this->app->alias(BudgetCommitmentPort::class, BudgetCommitmentService::class);

        // Bind SubledgerTotalProviders for Period Closing
        $this->app->tag([
            ArSubledgerTotalAdapter::class,
            ApSubledgerTotalAdapter::class,
            FixedAssetSubledgerTotalAdapter::class,
            InventorySubledgerTotalAdapter::class,
        ], SubledgerTotalProvider::class);

        // Bind ComplianceDeadlineSources
        $this->app->tag([
            TaxComplianceDeadlineSource::class,
            ContractComplianceDeadlineSource::class,
            RegulatoryLicenseComplianceDeadlineSource::class,
        ], ComplianceDeadlineSource::class);

        $this->app->singleton(FinancialPeriodClosingService::class, function ($app) {
            return new FinancialPeriodClosingService(
                ledgerPoster: $app->make(EnterpriseFinanceLedgerPoster::class),
                subledgerProviders: $app->tagged(SubledgerTotalProvider::class),
                outboxBus: $app->bound(OutboxBusInterface::class) ? $app->make(OutboxBusInterface::class) : null,
                clock: $app->bound(ClockInterface::class) ? $app->make(ClockInterface::class) : null
            );
        });

        $this->app->singleton(CorporateTaxReconciliationService::class);

        $this->app->singleton(SodGovernanceService::class, function ($app) {
            return new SodGovernanceService(
                outboxBus: $app->bound(OutboxBusInterface::class) ? $app->make(OutboxBusInterface::class) : null,
                clock: $app->bound(ClockInterface::class) ? $app->make(ClockInterface::class) : null
            );
        });

        $this->app->singleton(InternalControlTestingService::class, function ($app) {
            return new InternalControlTestingService(
                taskInboxService: $app->bound(TaskInboxService::class) ? $app->make(TaskInboxService::class) : null,
                clock: $app->bound(ClockInterface::class) ? $app->make(ClockInterface::class) : null
            );
        });

        $this->app->singleton(ComplianceEscalationService::class, function ($app) {
            return new ComplianceEscalationService(
                sources: $app->tagged(ComplianceDeadlineSource::class),
                outboxBus: $app->bound(OutboxBusInterface::class) ? $app->make(OutboxBusInterface::class) : null,
                clock: $app->bound(ClockInterface::class) ? $app->make(ClockInterface::class) : null
            );
        });

        $this->app->singleton(AuditDataPackGeneratorService::class);
        $this->app->singleton(GroupFinanceHealthService::class);
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

        if (is_dir(__DIR__.'/resources/views/enterprise_finance')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/enterprise_finance', 'enterprise_finance');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditEnterpriseFinanceCommand::class,
                EvaluateCovenantsCommand::class,
                AggregateTrialBalanceCommand::class,
                FreezeOverdueBudgetsCommand::class,
                CalculateWorkingCapitalCommand::class,
                ReconcileTaxPeriodCommand::class,
                ExportEnterpriseReportsCommand::class,
                AuditSodConflictsCommand::class,
                CheckComplianceDeadlinesCommand::class,
                // New Phase 54 Commands
                BudgetSyncCommand::class,
                CloseChecklistCommand::class,
                ComplianceEscalateCommand::class,
                ControlTestsCommand::class,
                DataPackCommand::class,
                SodScanCommand::class,
                TaxReconCommand::class,
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
            label: 'Finance Grup & Anggaran',
            route: 'enterprise_finance.index',
            icon: 'calculator',
            roles: ['admin', 'auditor', 'treasury', 'finance_controller', 'budget_owner'],
            order: 68,
            group: 'Keuangan',
            activePattern: 'enterprise-finance*',
        );
    }
}
