<?php

namespace Modules\Agri;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Agri\Console\Commands\AuditAgriCommand;

class AgriServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            AuditAgriCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'agri');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}
