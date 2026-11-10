<?php

declare(strict_types=1);

namespace Modules\Shared;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;
use Modules\Shared\Application\SearchRegistry;
use Modules\Shared\Console\Commands\DsAuditCommand;
use Modules\Shared\Console\Commands\DsTokensBuildCommand;
use Modules\Shared\Console\Commands\DsUsageScanCommand;
use Modules\Shared\Console\Commands\UiLintCommand;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class, fn () => new MenuRegistry);
        $this->app->singleton(SearchRegistry::class, fn () => new SearchRegistry);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'shared');

        // Allow components to be accessed via <x-card>, <x-stat>, etc. and <x-shared::card>
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', '');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', 'shared');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components/ds', 'ds');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UiLintCommand::class,
                DsTokensBuildCommand::class,
                DsUsageScanCommand::class,
                DsAuditCommand::class,
            ]);
        }
    }
}
