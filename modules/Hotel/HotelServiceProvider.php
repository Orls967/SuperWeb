<?php

declare(strict_types=1);

namespace Modules\Hotel;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\HotelFolioPort;
use Modules\Hotel\Application\Services\HotelFolioAdapter;

class HotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HotelFolioPort::class, HotelFolioAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
