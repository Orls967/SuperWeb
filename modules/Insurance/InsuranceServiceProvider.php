<?php

declare(strict_types=1);

namespace Modules\Insurance;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Insurance\Application\Services\FullInsuranceService;
use Modules\Insurance\Application\Services\InsurTechClaimsService;
use Modules\Insurance\Console\Commands\AuditEmbeddedCommand;
use Modules\Insurance\Console\Commands\AuditInsuranceCommand;
use Modules\Insurance\Console\Commands\AuditLifeHealthCommand;
use Modules\Insurance\Console\Commands\AuditReinsuranceCommand;
use Modules\Insurance\Console\Commands\CatRunCommand;
use Modules\Insurance\Console\Commands\CedePeriodCommand;
use Modules\Insurance\Console\Commands\SolvencySnapshotCommand;
use Modules\Shared\Application\MenuRegistry;

class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InsurTechClaimsService::class, function ($app) {
            return new InsurTechClaimsService(
                $app->make(SimClockInterface::class),
                $app->make(LedgerService::class)
            );
        });

        $this->mergeConfigFrom(__DIR__.'/config/insurance.php', 'insurance');

        $this->app->singleton(FullInsuranceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'insurance');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditInsuranceCommand::class,
                AuditReinsuranceCommand::class,
                AuditEmbeddedCommand::class,
                AuditLifeHealthCommand::class,
                CatRunCommand::class,
                CedePeriodCommand::class,
                SolvencySnapshotCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $menu = $this->app->make(MenuRegistry::class);
            $menu->addItem(
                label: 'Reasuransi & Treaty',
                route: 'insurance.reinsurance.treaties.index',
                icon: 'shield-check',
                roles: ['admin', 'superadmin', 'reinsurance_mgr'],
                order: 180,
                group: 'Asuransi'
            );
            $menu->addItem(
                label: 'Solvabilitas & RBC',
                route: 'insurance.capital.index',
                icon: 'chart-bar',
                roles: ['admin', 'superadmin', 'actuary', 'cfo_view'],
                order: 181,
                group: 'Asuransi'
            );
            $menu->addItem(
                label: 'CAT Modelling',
                route: 'insurance.cat.index',
                icon: 'lightning-bolt',
                roles: ['admin', 'superadmin', 'actuary'],
                order: 182,
                group: 'Asuransi'
            );
            $menu->addItem(
                label: 'Catastrophe Bonds',
                route: 'insurance.cat-bonds.index',
                icon: 'document-report',
                roles: ['admin', 'superadmin', 'reinsurance_mgr'],
                order: 183,
                group: 'Asuransi'
            );
            $menu->addItem(
                label: 'Portal Investor Cat Bond',
                route: 'investor.cat-bonds.index',
                icon: 'user-group',
                roles: ['admin', 'superadmin', 'investor', 'reinsurance_mgr'],
                order: 184,
                group: 'Investor'
            );
        }
    }
}
