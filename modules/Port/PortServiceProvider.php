<?php

declare(strict_types=1);

namespace Modules\Port;

use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Port\Console\Commands\AuditPortCommand;
use Modules\Port\Ledger\PortTransactionTypes;

class PortServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings if needed
    }

    public function boot(): void
    {
        TransactionTypeRegistry::register(PortTransactionTypes::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'port');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditPortCommand::class,
            ]);
        }
    }
}
