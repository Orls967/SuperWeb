<?php

declare(strict_types=1);

namespace Modules\Manufacturing;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Manufacturing\Application\Listeners\PostSaleCogsListener;
use Modules\Manufacturing\Application\Services\CostingService;
use Modules\Manufacturing\Application\Services\HseService;
use Modules\Manufacturing\Application\Services\MaintenanceService;
use Modules\Manufacturing\Application\Services\ManufacturingService;
use Modules\Manufacturing\Application\Services\PlanningService;
use Modules\Manufacturing\Application\Services\ProductionService;
use Modules\Manufacturing\Application\Services\QualityService;
use Modules\Manufacturing\Console\Commands\AuditCostingCommand;
use Modules\Manufacturing\Console\Commands\QmsAuditCommand;
use Modules\Manufacturing\Console\Commands\RunMrpCommand;
use Modules\Manufacturing\Console\Commands\WipReportCommand;
use Modules\Shared\Application\MenuRegistry;
use Modules\Store\Domain\Events\OrderPaid;

class ManufacturingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ManufacturingService::class);
        $this->app->singleton(PlanningService::class);
        $this->app->singleton(ProductionService::class);
        $this->app->singleton(CostingService::class);
        $this->app->singleton(QualityService::class);
        $this->app->singleton(MaintenanceService::class);
        $this->app->singleton(HseService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/manufacturing')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/manufacturing', 'manufacturing');
        }

        Event::listen(OrderPaid::class, PostSaleCogsListener::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RunMrpCommand::class, WipReportCommand::class, AuditCostingCommand::class, QmsAuditCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Pabrik',
            route: 'manufacturing.index',
            icon: 'cog',
            roles: ['admin', 'planner', 'operator', 'qc_inspector'],
            order: 50,
            group: 'Produksi',
            activePattern: 'manufacturing*',
        );
    }
}
