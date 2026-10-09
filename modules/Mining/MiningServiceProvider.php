<?php

declare(strict_types=1);

namespace Modules\Mining;

use Illuminate\Support\ServiceProvider;

class MiningServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if any
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
