<?php

declare(strict_types=1);

namespace Modules\Partner;

use Illuminate\Support\ServiceProvider;
use Modules\Partner\Application\Services\PartnerService;
use Modules\Partner\Console\Commands\AuditPartnerCommand;
use Modules\Shared\Application\MenuRegistry;

class PartnerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PartnerService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        if (is_dir(__DIR__.'/resources/views/partner')) {
            $this->loadViewsFrom(__DIR__.'/resources/views/partner', 'partner');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([AuditPartnerCommand::class]);
        }

        $this->registerMenu();
    }

    protected function registerMenu(): void
    {
        if (! $this->app->bound(MenuRegistry::class)) {
            return;
        }

        $this->app->make(MenuRegistry::class)->addItem(
            label: 'Mitra',
            route: 'partners.index',
            icon: 'handshake',
            roles: ['admin', 'procurement', 'auditor'],
            order: 59,
            group: 'Logistik',
            activePattern: 'partners*',
        );
    }
}
