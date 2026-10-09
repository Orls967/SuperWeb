<?php

namespace Modules\Epc;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Epc\Console\Commands\AuditEpcCommand;

class EpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            AuditEpcCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'epc');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__.'/routes/web.php');
        }
    }
}
