<?php

declare(strict_types=1);

namespace Modules\Treasury;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Treasury\Application\Services\TreasuryService;
use Modules\Treasury\Console\Commands\AuditTreasuryCommand;

class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TreasuryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/treasury')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/treasury', 'treasury');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditTreasuryCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Treasury & Valas',
            route: 'treasury.index',
            icon: 'banknotes',
            roles: ['admin', 'auditor', 'treasury'],
            order: 62,
            group: 'Keuangan',
            activePattern: 'treasury*',
        );
    }
}
