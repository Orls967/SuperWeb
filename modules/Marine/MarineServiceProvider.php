<?php

declare(strict_types=1);

namespace Modules\Marine;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Marine\UI\Console\Commands\AuditMarineCommand;

class MarineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if any
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'marine');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')->group(__DIR__.'/routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditMarineCommand::class,
            ]);
        }
    }
}
