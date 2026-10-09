<?php

declare(strict_types=1);

namespace Modules\International;

use Illuminate\Support\ServiceProvider;
use Modules\International\Application\Services\InternationalService;
use Modules\International\Console\Commands\AuditInternationalCommand;
use Modules\Shared\Application\MenuRegistry;

class InternationalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InternationalService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/international')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/international', 'international');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditInternationalCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Kerja Sama Internasional',
            route: 'international.index',
            icon: 'globe-alt',
            roles: ['admin', 'auditor', 'partner', 'legal'],
            order: 65,
            group: 'Bisnis & Kemitraan',
            activePattern: 'international*',
        );
    }
}
