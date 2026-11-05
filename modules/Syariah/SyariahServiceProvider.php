<?php

declare(strict_types=1);

namespace Modules\Syariah;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Registry\TransactionTypeRegistry;
use Modules\Syariah\Console\Commands\AuditSyariahCommand;
use Modules\Syariah\Console\Commands\IjarahBillingScheduledCommand;
use Modules\Syariah\Console\Commands\SukukDistributeScheduledCommand;
use Modules\Syariah\Console\Commands\ZakatHaulAssessScheduledCommand;
use Modules\Syariah\Domain\Models\MurabahahContract;
use Modules\Syariah\Http\Policies\MurabahahContractPolicy;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

class SyariahServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings resolved via autowiring
    }

    public function boot(): void
    {
        TransactionTypeRegistry::register(SyariahTransactionTypes::class);
        Gate::policy(MurabahahContract::class, MurabahahContractPolicy::class);

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'syariah');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditSyariahCommand::class,
                IjarahBillingScheduledCommand::class,
                SukukDistributeScheduledCommand::class,
                ZakatHaulAssessScheduledCommand::class,
            ]);
        }
    }
}
