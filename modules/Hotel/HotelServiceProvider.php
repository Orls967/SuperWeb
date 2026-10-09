<?php

declare(strict_types=1);

namespace Modules\Hotel;

use Illuminate\Support\ServiceProvider;

class HotelServiceProvider extends ServiceProvider
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
