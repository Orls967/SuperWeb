<?php

namespace Modules\Proptech;

use Illuminate\Support\ServiceProvider;
use Modules\Proptech\Application\Services\ProptechService;

class ProptechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProptechService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
