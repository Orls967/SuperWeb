<?php

declare(strict_types=1);

namespace Modules\Finance;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Crypto\Domain\Events\PricesTicked;
use Modules\Finance\Console\Commands\ChargeDueInstallmentsCommand;
use Modules\Finance\Domain\Models\Loan;
use Modules\Finance\Listeners\MonitorLoanRisk;
use Modules\Shared\Application\MenuRegistry;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'finance');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        Relation::morphMap([
            'fin_loan' => Loan::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ChargeDueInstallmentsCommand::class,
            ]);
        }

        // Risk monitor: cek LTV setiap harga kripto berubah
        Event::listen(PricesTicked::class, MonitorLoanRisk::class);

        $registry = $this->app->make(MenuRegistry::class);
        $registry->addItem(
            label: 'HODL-to-Drive',
            route: 'finance.loans.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            roles: [],
            order: 60,
            group: 'Pembiayaan',
            activePattern: 'finance.*',
        );
    }
}
