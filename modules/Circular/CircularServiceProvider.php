<?php

declare(strict_types=1);

namespace Modules\Circular;

use Illuminate\Support\ServiceProvider;
use Modules\Circular\Console\Commands\AuditCircularCommand;

class CircularServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings if needed
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'circular');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCircularCommand::class,
            ]);
        }
    }
}

