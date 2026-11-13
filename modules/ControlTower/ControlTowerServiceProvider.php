<?php

declare(strict_types=1);

namespace Modules\ControlTower;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\ControlTower\Application\Services\ControlTowerService;
use Modules\ControlTower\Application\Services\SopPublicationOrchestrator;
use Modules\ControlTower\Console\Commands\AuditControlTowerCommand;
use Modules\ControlTower\Console\Commands\CaptureKpiSnapshotCommand;
use Modules\ControlTower\Console\Commands\RecalculateSafetyStockCommand;
use Modules\ControlTower\Console\Commands\RunTwinSimulationCommand;
use Modules\ControlTower\Contracts\ControlTowerVisibilityReader;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;
use Modules\ControlTower\Infrastructure\Adapters\ControlTowerVisibilityReaderAdapter;
use Modules\ControlTower\Policies\ControlTowerOperationPolicy;
use Modules\Core\Application\Actions\TaskInbox\TaskInboxService;
use Modules\Core\Application\Services\EventSchemaRegistry;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Shared\Application\MenuRegistry;

class ControlTowerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ControlTowerService::class);
        $this->app->bind(ControlTowerVisibilityReader::class, ControlTowerVisibilityReaderAdapter::class);

        // [fix2:bindings:promise]
        //
        // [fix2:bindings:forecast]
        //
        // [fix2:bindings:safety]
        //
        // [fix2:bindings:sop]
        $this->app->singleton(SopCycleStateMachine::class);
        $this->app->bind(SopPublicationOrchestrator::class, function ($app): SopPublicationOrchestrator {
            return new SopPublicationOrchestrator(
                stateMachine: $app->make(SopCycleStateMachine::class),
                clock: $app->make(ClockInterface::class),
                taskInbox: $app->make(TaskInboxService::class),
                approvalEngine: $app->bound(ApprovalEngineInterface::class) ? $app->make(ApprovalEngineInterface::class) : null,
                outboxBus: $app->bound(OutboxBusInterface::class) ? $app->make(OutboxBusInterface::class) : null,
                schemaRegistry: $app->bound(EventSchemaRegistry::class) ? $app->make(EventSchemaRegistry::class) : null,
            );
        });
        //
        // [fix2:bindings:alerts]
        //
        // [fix2:bindings:twin_kpi]
        //
        // [fix2:bindings:scorecard_health]
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Gate::policy(SopCycle::class, ControlTowerOperationPolicy::class);
        // [fix2:boot:promise]
        //
        // [fix2:boot:forecast]
        //
        // [fix2:boot:safety]
        //
        // [fix2:boot:sop]
        //
        // [fix2:boot:alerts]
        //
        // [fix2:boot:twin_kpi]
        //
        // [fix2:boot:scorecard_health]
        //

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // [fix2:schedule:promise]
            //
            // [fix2:schedule:forecast]
            //
            // [fix2:schedule:safety]
            //
            // [fix2:schedule:sop]
            //
            // [fix2:schedule:alerts]
            //
            // [fix2:schedule:twin_kpi]
            //
            // [fix2:schedule:scorecard_health]
            //
        });

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }
        foreach (glob(__DIR__.'/routes/slice_*.php') ?: [] as $sliceRoutes) {
            $this->loadRoutesFrom($sliceRoutes);
        }

        if (is_dir(__DIR__.'/resources/views/control_tower')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/control_tower', 'control_tower');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditControlTowerCommand::class,
                CaptureKpiSnapshotCommand::class,
                RecalculateSafetyStockCommand::class,
                RunTwinSimulationCommand::class,
                // [fix2:commands:promise]
                //
                // [fix2:commands:forecast]
                //
                // [fix2:commands:safety]
                //
                // [fix2:commands:sop]
                //
                // [fix2:commands:alerts]
                //
                // [fix2:commands:twin_kpi]
                //
                // [fix2:commands:scorecard_health]
                //
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
            label: 'Control Tower & S&OP',
            route: 'control_tower.index',
            icon: 'presentation-chart-line',
            roles: ['admin', 'auditor', 'planner', 'hub_operator'],
            order: 67,
            group: 'Operasi & Logistik',
            activePattern: 'control-tower*',
        );
    }
}
