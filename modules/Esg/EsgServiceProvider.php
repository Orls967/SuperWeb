<?php

namespace Modules\Esg;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Esg\Console\Commands\AuditEsgCommand;

class EsgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            AuditEsgCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'esg');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}
