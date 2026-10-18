<?php

declare(strict_types=1);

namespace Modules\Core;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Application\Actions\TransferVehicleOwnershipAction;
use Modules\Core\Application\Listeners\RecordVehicleAcquiredPassportEvent;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\Ai\DynamicPricingEngineService;
use Modules\Core\Application\Services\Ai\StockPrescriptionEngineService;
use Modules\Core\Application\Services\Ai\TransactionAnomalyDetectorService;
use Modules\Core\Application\Services\ApprovalEngineService;
use Modules\Core\Application\Services\AuditTrailService;
use Modules\Core\Application\Services\CrossModuleSpineHandlers;
use Modules\Core\Application\Services\DigitalTwinService;
use Modules\Core\Application\Services\DocumentNumberingService;
use Modules\Core\Application\Services\DocumentStoreService;
use Modules\Core\Application\Services\EventSpineService;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Core\Application\Services\RbacService;
use Modules\Core\Application\Services\Resilience\DataSovereigntyComplianceService;
use Modules\Core\Application\Services\Resilience\DisasterRecoveryDrillService;
use Modules\Core\Application\Services\Resilience\MultiRegionResilienceEngineService;
use Modules\Core\Application\Services\Scale\OutboxScaleProvisioner;
use Modules\Core\Application\Services\Scale\PassportScaleProvisioner;
use Modules\Core\Application\Services\SimClockService;
use Modules\Core\Console\Commands\AuditAiCommand;
use Modules\Core\Console\Commands\AuditMobileCommand;
use Modules\Core\Console\Commands\BenchmarkRunCommand;
use Modules\Core\Console\Commands\ConsumeSpineCommand;
use Modules\Core\Console\Commands\ProcessOutboxCommand;
use Modules\Core\Console\Commands\RunSimulationCommand;
use Modules\Core\Console\Commands\ScaleProvisionCommand;
use Modules\Core\Console\Commands\SuperHealthCheckCommand;
use Modules\Core\Console\Commands\TwelveLinesComprehensiveAuditCommand;
use Modules\Core\Console\Commands\VerifyPassportsCommand;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Core\Contracts\DocumentStoreInterface;
use Modules\Core\Contracts\DynamicPricingEngineInterface;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Contracts\StockPrescriptionEngineInterface;
use Modules\Core\Contracts\TransactionAnomalyDetectorInterface;
use Modules\Core\Contracts\TransfersVehicleOwnership;
use Modules\Core\Domain\Contracts\DataSovereigntyComplianceInterface;
use Modules\Core\Domain\Contracts\DisasterRecoveryDrillInterface;
use Modules\Core\Domain\Contracts\MultiRegionResilienceInterface;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\MenuRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AcquiresVehicle::class,
            AcquireVehicleAction::class
        );

        $this->app->bind(
            TransfersVehicleOwnership::class,
            TransferVehicleOwnershipAction::class
        );

        $this->app->bind(
            AuditTrailInterface::class,
            AuditTrailService::class
        );

        $this->app->bind(
            OutboxBusInterface::class,
            OutboxBusService::class
        );

        $this->app->bind(
            DocumentNumberingInterface::class,
            DocumentNumberingService::class
        );

        $this->app->bind(
            DocumentStoreInterface::class,
            DocumentStoreService::class
        );

        $this->app->bind(
            ApprovalEngineInterface::class,
            ApprovalEngineService::class
        );

        $this->app->bind(
            ClockInterface::class,
            SimClockService::class
        );

        $this->app->bind(
            SimClockInterface::class,
            SimClockService::class
        );

        $this->app->bind(
            EventSpineInterface::class,
            EventSpineService::class
        );

        $this->app->bind(
            DigitalTwinInterface::class,
            DigitalTwinService::class
        );

        // 64.1 - 64.3 AI Predictive Analytics & Governance Engines
        $this->app->bind(
            DynamicPricingEngineInterface::class,
            DynamicPricingEngineService::class
        );

        $this->app->bind(
            TransactionAnomalyDetectorInterface::class,
            TransactionAnomalyDetectorService::class
        );

        $this->app->bind(
            StockPrescriptionEngineInterface::class,
            StockPrescriptionEngineService::class
        );

        // 66.1 - 66.2 Multi-Region Resilience, DR & Data Sovereignty
        $this->app->bind(
            DisasterRecoveryDrillInterface::class,
            DisasterRecoveryDrillService::class
        );

        $this->app->bind(
            MultiRegionResilienceInterface::class,
            MultiRegionResilienceEngineService::class
        );

        $this->app->bind(
            DataSovereigntyComplianceInterface::class,
            DataSovereigntyComplianceService::class
        );

        // Platform services — singletons so they can be injected anywhere
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(ActivityLogger::class);
        $this->app->singleton(RbacService::class);
        $this->app->singleton(AuditTrailService::class);
        $this->app->singleton(OutboxBusService::class);
        $this->app->singleton(DocumentNumberingService::class);
        $this->app->singleton(DocumentStoreService::class);
        $this->app->singleton(ApprovalEngineService::class);
        $this->app->singleton(SimClockService::class);
        $this->app->singleton(EventSpineService::class);
        $this->app->singleton(DigitalTwinService::class);
        $this->app->singleton(DisasterRecoveryDrillService::class);
        $this->app->singleton(MultiRegionResilienceEngineService::class);
        $this->app->singleton(DataSovereigntyComplianceService::class);

        $this->app->tag([
            PassportScaleProvisioner::class,
            OutboxScaleProvisioner::class,
        ], 'scale.provisioner');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'core');

        Relation::morphMap([
            'core_vehicle' => Vehicle::class,
        ]);

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        // === Gate: RBAC permission-based authorization ===
        // Admin bypasses all gates; otherwise check RBAC permissions
        Gate::before(function ($user, $ability) {
            if ($user->role === 'admin' || $user->hasRbacRole('admin')) {
                return true;
            }

            // Check if the user has the permission through RBAC
            if ($user->hasRbacPermission($ability)) {
                return true;
            }

            // Return null to let the policy/gate decide
            return null;
        });

        // Register menu items
        $registry = $this->app->make(MenuRegistry::class);
        $registry->addItem(
            label: 'Dashboard',
            route: 'dashboard',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
            roles: [],
            order: 1,
            group: 'Core',
            activePattern: 'dashboard',
        );

        $registry->addItem(
            label: 'Kesehatan Sistem',
            route: 'admin.health.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
            roles: ['admin'],
            order: 99,
            group: 'Grup & Admin',
            activePattern: 'admin/health*',
        );

        $registry->addItem(
            label: 'Roles & Permissions',
            route: 'admin.rbac.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            roles: ['admin'],
            order: 98,
            group: 'Grup & Admin',
            activePattern: 'admin/rbac*',
        );

        $registry->addItem(
            label: 'Audit Trail',
            route: 'admin.audit-logs.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            roles: ['admin'],
            order: 97,
            group: 'Grup & Admin',
            activePattern: 'admin/audit-logs*',
        );

        $registry->addItem(
            label: 'AI & Analitik Governance',
            route: 'admin.ai.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
            roles: ['admin'],
            order: 96,
            group: 'Grup & Admin',
            activePattern: 'admin/ai-governance*',
        );

        $registry->addItem(
            label: 'Mobile Suite Governance',
            route: 'admin.mobile.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
            roles: ['admin'],
            order: 95,
            group: 'Grup & Admin',
            activePattern: 'admin/mobile-governance*',
        );

        $registry->addItem(
            label: 'Resiliensi & DR Multi-Region',
            route: 'admin.resilience.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            roles: ['admin'],
            order: 94,
            group: 'Grup & Admin',
            activePattern: 'admin/resilience-governance*',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyPassportsCommand::class,
                SuperHealthCheckCommand::class,
                ProcessOutboxCommand::class,
                ConsumeSpineCommand::class,
                RunSimulationCommand::class,
                TwelveLinesComprehensiveAuditCommand::class,
                ScaleProvisionCommand::class,
                BenchmarkRunCommand::class,
                AuditAiCommand::class,
                AuditMobileCommand::class,
            ]);
        }

        // === Event Listeners ===
        Event::listen(VehicleAcquired::class, RecordVehicleAcquiredPassportEvent::class);

        // === Event Spine Cross-Module Consumers ===
        CrossModuleSpineHandlers::registerAll();
    }
}
