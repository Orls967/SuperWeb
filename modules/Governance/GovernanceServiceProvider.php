<?php

declare(strict_types=1);

namespace Modules\Governance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Governance\Application\Services\DaoGovernanceService;
use Modules\Governance\Console\Commands\AuditGovernanceCommand;
use Modules\Governance\Contracts\ProposalDirectory;

class GovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProposalDirectory::class, DaoGovernanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'governance');

        if (file_exists(__DIR__.'/routes/web.php')) {
            Route::middleware('web')->group(__DIR__.'/routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditGovernanceCommand::class,
            ]);
        }
    }
}
