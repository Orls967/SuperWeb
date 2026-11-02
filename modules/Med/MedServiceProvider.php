<?php

declare(strict_types=1);

namespace Modules\Med;

use Illuminate\Support\ServiceProvider;
use Modules\Med\Console\Commands\MedAuditCommand;
use Modules\Med\Console\Commands\SettleMediaRoyaltiesCommand;
use Modules\Shared\Application\MenuRegistry;

class MedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if needed
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'med');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MedAuditCommand::class,
                SettleMediaRoyaltiesCommand::class,
            ]);
        }

        if ($this->app->bound(MenuRegistry::class)) {
            $this->app->make(MenuRegistry::class)->addItem(
                label: 'Media & Kreatif',
                route: 'media.index',
                icon: 'film',
                roles: ['producer', 'studio_ops', 'ip_manager', 'talent_mgmt'],
                order: 150,
            );
        }
    }
}
