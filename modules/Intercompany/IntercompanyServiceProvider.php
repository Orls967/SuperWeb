<?php

declare(strict_types=1);

namespace Modules\Intercompany;

use Illuminate\Support\ServiceProvider;
use Modules\Intercompany\Application\Services\IntercompanyService;
use Modules\Intercompany\Console\Commands\AuditIntercompanyCommand;
use Modules\Shared\Application\MenuRegistry;

class IntercompanyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntercompanyService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/intercompany')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/intercompany', 'intercompany');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditIntercompanyCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Konsolidasi Grup & IC',
            route: 'intercompany.index',
            icon: 'library',
            roles: ['admin', 'auditor', 'treasury'],
            order: 66,
            group: 'Keuangan',
            activePattern: 'intercompany*',
        );
    }
}
