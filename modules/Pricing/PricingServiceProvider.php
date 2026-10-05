<?php

declare(strict_types=1);

namespace Modules\Pricing;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Pricing\Application\Listeners\PostSalePriceLockListener;
use Modules\Pricing\Application\Services\PricingService;
use Modules\Pricing\Console\Commands\AuditPricingCommand;
use Modules\Pricing\Contracts\PriceLocker;
use Modules\Shared\Application\MenuRegistry;
use Modules\Store\Domain\Events\OrderPaid;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PricingService::class);
        $this->app->bind(PriceLocker::class, PricingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/pricing')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/pricing', 'pricing');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditPricingCommand::class]);
        }

        Event::listen(OrderPaid::class, PostSalePriceLockListener::class);

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Harga & Promo',
            route: 'pricing.index',
            icon: 'tag',
            roles: ['admin', 'procurement', 'distributor', 'auditor'],
            order: 57,
            group: 'Logistik',
            activePattern: 'pricing*',
        );
    }
}
