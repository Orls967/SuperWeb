<?php

declare(strict_types=1);

namespace Modules\Payment;

use Illuminate\Support\ServiceProvider;
use Modules\Payment\Application\Services\PaymentGatewayService;
use Modules\Payment\Console\Commands\ReleaseExpiredHoldsCommand;
use Modules\Payment\Contracts\PaymentGateway;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, PaymentGatewayService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'payment');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseExpiredHoldsCommand::class,
            ]);
        }
    }
}
