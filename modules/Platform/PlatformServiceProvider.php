<?php

declare(strict_types=1);

namespace Modules\Platform;

use App\Console\Commands\DocsCheckCommand;
use App\Console\Commands\DocsDriftCommand;
use App\Console\Commands\DocsExportCommand;
use App\Console\Commands\DocsGenerateCommand;
use App\Console\Commands\QualityCoverageGapCommand;
use App\Console\Commands\QualityPhaseMetricsCommand;
use App\Console\Commands\QualityPipelineStatusCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Console\Commands\PlatformAnonymizeCommand;
use Modules\Platform\Console\Commands\PlatformAuditCommand;
use Modules\Platform\Console\Commands\PlatformErrorBudgetCommand;
use Modules\Platform\Console\Commands\PlatformMaskVerifyCommand;
use Modules\Platform\Console\Commands\PlatformSynthDataCommand;
use Modules\Platform\Console\Commands\SandboxExpireCommand;
use Modules\Platform\Domain\Actions\AnonymizeDatasetAction;
use Modules\Platform\Domain\Actions\CalculateErrorBudgetAction;
use Modules\Platform\Domain\Actions\CalculatePhaseMetricsAction;
use Modules\Platform\Domain\Actions\CollectPipelineStatusAction;
use Modules\Platform\Domain\Actions\CorrelationContextHolder;
use Modules\Platform\Domain\Actions\CreateSandboxAction;
use Modules\Platform\Domain\Actions\DestroySandboxAction;
use Modules\Platform\Domain\Actions\EvaluateCoverageGapAction;
use Modules\Platform\Domain\Actions\EvaluateMultiWindowBurnRateAction;
use Modules\Platform\Domain\Actions\ExportDocumentationBundleAction;
use Modules\Platform\Domain\Actions\ManageSandboxQuotaAction;
use Modules\Platform\Domain\Actions\MaskPiiAction;
use Modules\Platform\Domain\Actions\ProvisionSandboxAction;
use Modules\Platform\Domain\Actions\RecordPostmortemAction;
use Modules\Platform\Domain\Actions\RotatePiiSaltAction;
use Modules\Platform\Domain\Actions\TraceCorrelationChainAction;
use Modules\Platform\Domain\Actions\TrackServiceSloAction;
use Modules\Platform\Domain\Actions\VerifyMaskingAction;
use Modules\Platform\Domain\Chaos\DatabaseTamperSimulator;
use Modules\Platform\Domain\Chaos\DataLeakageTamperSimulator;
use Modules\Platform\Domain\Chaos\LatencyInjectionSimulator;
use Modules\Platform\Domain\Events\PlatformEventDispatcher;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Services\CorrelationTracerService;
use Modules\Platform\Domain\Services\DeterministicDataFactory;
use Modules\Platform\Domain\Services\MaskRuleRepository;
use Modules\Platform\Domain\Services\PiiDeterministicTokenVault;
use Modules\Platform\Domain\Services\PiiRedactionPipeline;
use Modules\Platform\Domain\Services\PiiVaultAnonymizer;
use Modules\Platform\Domain\Services\PlatformAuditReportBuilder;
use Modules\Platform\Domain\Services\QualityPhaseRatchetEvaluator;
use Modules\Platform\Domain\Services\QualityRatchetDebtService;
use Modules\Platform\Domain\Services\SandboxDatabaseMigrator;
use Modules\Platform\Domain\Services\SandboxGarbageCollector;
use Modules\Platform\Domain\Services\SandboxHealthCheckService;
use Modules\Platform\Domain\Services\SandboxIsolationService;
use Modules\Platform\Domain\Services\SandboxLifecycleAuditSubscriber;
use Modules\Platform\Domain\Services\SandboxLogAggregator;
use Modules\Platform\Domain\Services\SandboxNetworkingService;
use Modules\Platform\Domain\Services\SandboxSeederService;
use Modules\Platform\Domain\Services\ServiceLevelObjectiveEvaluator;
use Modules\Platform\Domain\Services\SreAlertRouter;
use Modules\Platform\Domain\Services\SyntheticDataSchemaCompiler;
use Modules\Platform\Domain\Services\W3cTraceContextPropagator;
use Modules\Platform\Domain\Telemetry\AuditTrailIntegrityVerifier;
use Modules\Platform\Domain\Telemetry\PlatformNotificationRouter;
use Modules\Platform\Domain\Telemetry\ServiceTelemetryCollector;
use Modules\Platform\Domain\Telemetry\SyntheticTrafficGenerator;
use Modules\Platform\Policies\SandboxEnvironmentPolicy;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/platform.php',
            'platform'
        );

        // Binding Singleton Context & Vault
        $this->app->singleton(CorrelationContextHolder::class);
        $this->app->singleton(PiiDeterministicTokenVault::class);
        $this->app->singleton(PiiVaultAnonymizer::class);
        $this->app->singleton(PiiRedactionPipeline::class);
        $this->app->singleton(CorrelationTracerService::class);
        $this->app->singleton(W3cTraceContextPropagator::class);
        $this->app->singleton(SreAlertRouter::class);
        $this->app->singleton(ServiceLevelObjectiveEvaluator::class);
        $this->app->singleton(PlatformNotificationRouter::class);
        $this->app->singleton(AuditTrailIntegrityVerifier::class);
        $this->app->singleton(ServiceTelemetryCollector::class);
        $this->app->singleton(SyntheticTrafficGenerator::class);
        $this->app->singleton(PlatformEventDispatcher::class);
        $this->app->singleton(MaskRuleRepository::class);
        $this->app->singleton(QualityPhaseRatchetEvaluator::class);
        $this->app->singleton(QualityRatchetDebtService::class);
        $this->app->singleton(PlatformAuditReportBuilder::class);
        $this->app->singleton(SandboxNetworkingService::class);
        $this->app->singleton(SandboxDatabaseMigrator::class);
        $this->app->singleton(DeterministicDataFactory::class);
        $this->app->singleton(SyntheticDataSchemaCompiler::class);
        $this->app->singleton(SandboxGarbageCollector::class);
        $this->app->singleton(SandboxSeederService::class);
        $this->app->singleton(SandboxIsolationService::class);
        $this->app->singleton(SandboxHealthCheckService::class);
        $this->app->singleton(SandboxLogAggregator::class);
        $this->app->singleton(LatencyInjectionSimulator::class);
        $this->app->singleton(DatabaseTamperSimulator::class);
        $this->app->singleton(DataLeakageTamperSimulator::class);

        // Binding Actions
        $this->app->bind(CreateSandboxAction::class);
        $this->app->bind(ProvisionSandboxAction::class);
        $this->app->bind(DestroySandboxAction::class);
        $this->app->bind(MaskPiiAction::class);
        $this->app->bind(VerifyMaskingAction::class);
        $this->app->bind(AnonymizeDatasetAction::class);
        $this->app->bind(RotatePiiSaltAction::class);
        $this->app->bind(CalculateErrorBudgetAction::class);
        $this->app->bind(TrackServiceSloAction::class);
        $this->app->bind(RecordPostmortemAction::class);
        $this->app->bind(EvaluateMultiWindowBurnRateAction::class);
        $this->app->bind(TraceCorrelationChainAction::class);
        $this->app->bind(CalculatePhaseMetricsAction::class);
        $this->app->bind(EvaluateCoverageGapAction::class);
        $this->app->bind(CollectPipelineStatusAction::class);
        $this->app->bind(ExportDocumentationBundleAction::class);
        $this->app->bind(ManageSandboxQuotaAction::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'platform');

        // Daftarkan Policy
        Gate::policy(SandboxEnvironment::class, SandboxEnvironmentPolicy::class);

        // Daftarkan Event Subscribers
        Event::subscribe(SandboxLifecycleAuditSubscriber::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                QualityPhaseMetricsCommand::class,
                QualityCoverageGapCommand::class,
                QualityPipelineStatusCommand::class,
                DocsGenerateCommand::class,
                DocsCheckCommand::class,
                DocsExportCommand::class,
                DocsDriftCommand::class,
                SandboxExpireCommand::class,
                PlatformSynthDataCommand::class,
                PlatformMaskVerifyCommand::class,
                PlatformAnonymizeCommand::class,
                PlatformErrorBudgetCommand::class,
                PlatformAuditCommand::class,
            ]);
        }
    }
}
