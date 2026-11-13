<?php

declare(strict_types=1);

namespace Modules\ControlTower;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\ControlTower\Application\Services\ControlTowerService;
use Modules\ControlTower\Console\Commands\AuditControlTowerCommand;
use Modules\ControlTower\Console\Commands\CaptureKpiSnapshotCommand;
use Modules\ControlTower\Console\Commands\RecalculateSafetyStockCommand;
use Modules\ControlTower\Console\Commands\RunTwinSimulationCommand;
use Modules\ControlTower\Contracts\ControlTowerVisibilityReader;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Infrastructure\Adapters\ControlTowerVisibilityReaderAdapter;
use Modules\ControlTower\Policies\ControlTowerOperationPolicy;
use Modules\Shared\Application\MenuRegistry;

class ControlTowerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ControlTowerService::class);
        $this->app->bind(ControlTowerVisibilityReader::class, ControlTowerVisibilityReaderAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Gate::policy(SopCycle::class, ControlTowerOperationPolicy::class);

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
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
