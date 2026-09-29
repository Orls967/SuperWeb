<?php

declare(strict_types=1);

namespace Modules\Crypto;

use Illuminate\Support\ServiceProvider;
use Modules\Crypto\Application\Services\PriceEngineService;
use Modules\Crypto\Console\Commands\CryptoTickCommand;
use Modules\Crypto\Contracts\PriceFeed;

class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PriceFeed::class, PriceEngineService::class);
        $this->app->singleton(PriceEngineService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'crypto');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CryptoTickCommand::class,
            ]);
        }
    }
}
