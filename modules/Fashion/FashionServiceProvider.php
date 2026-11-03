<?php

declare(strict_types=1);

namespace Modules\Fashion;

use Illuminate\Support\ServiceProvider;
use Modules\Fashion\Console\Commands\AuditFashionCommand;

class FashionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'fashion');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditFashionCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        // Bindings if any
    }
}
