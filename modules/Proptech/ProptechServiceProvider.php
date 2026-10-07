<?php

namespace Modules\Proptech;

use Illuminate\Support\ServiceProvider;
use Modules\Proptech\Application\Services\BimTwinService;
use Modules\Proptech\Application\Services\ProptechService;

class ProptechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProptechService::class);
        $this->app->singleton(BimTwinService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
