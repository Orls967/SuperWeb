<?php

declare(strict_types=1);

namespace Modules\ControlTower;

use Illuminate\Support\ServiceProvider;
use Modules\ControlTower\Application\Services\ControlTowerService;
use Modules\ControlTower\Console\Commands\AuditControlTowerCommand;
use Modules\Shared\Application\MenuRegistry;

class ControlTowerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ControlTowerService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/control_tower')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/control_tower', 'control_tower');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditControlTowerCommand::class]);
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
