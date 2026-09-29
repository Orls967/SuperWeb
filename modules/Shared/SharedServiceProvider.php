<?php

declare(strict_types=1);

namespace Modules\Shared;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\MenuRegistry;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class, fn () => new MenuRegistry);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'shared');

        // Allow components to be accessed via <x-card>, <x-stat>, etc. and <x-shared::card>
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', '');
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components', 'shared');
    }
}
