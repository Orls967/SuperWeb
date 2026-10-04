<?php

declare(strict_types=1);

namespace Modules\Asset;

use Illuminate\Support\ServiceProvider;
use Modules\Asset\Application\Services\AssetAuditService;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Application\Services\AssetTcoService;
use Modules\Asset\Application\Services\DepreciationService;
use Modules\Asset\Application\Services\LeaseService;
use Modules\Asset\Application\Services\RevaluationService;
use Modules\Asset\Application\Services\WorkOrderService;
use Modules\Asset\Console\Commands\AuditAssetCommand;
use Modules\Asset\Console\Commands\BackfillAssetLinksCommand;
use Modules\Asset\Console\Commands\CapitalizeAssetsCommand;
use Modules\Asset\Console\Commands\DepreciateAssetsCommand;
use Modules\Asset\Console\Commands\VerifyAssetChainsCommand;
use Modules\Shared\Application\MenuRegistry;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AssetService::class);
        $this->app->singleton(DepreciationService::class);
        $this->app->singleton(WorkOrderService::class);
        $this->app->singleton(LeaseService::class);
        $this->app->singleton(RevaluationService::class);
        $this->app->singleton(AssetTcoService::class);
        $this->app->singleton(AssetAuditService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views/asset', 'asset');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyAssetChainsCommand::class,
                BackfillAssetLinksCommand::class,
                DepreciateAssetsCommand::class,
                AuditAssetCommand::class,
                CapitalizeAssetsCommand::class,
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
            label: 'Aset',
            route: 'asset.index',
            icon: 'archive',
            roles: ['admin', 'asset_manager'],
            order: 48,
            group: 'Master Data',
            activePattern: 'asset*',
        );
    }
}
