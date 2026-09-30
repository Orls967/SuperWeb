<?php

declare(strict_types=1);

namespace Modules\Banking;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Console\Commands\ReconcileBankLedgerCommand;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Listeners\CreateUserWalletListener;
use Modules\Shared\Application\MenuRegistry;

class BankingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Ledger::class, LedgerService::class);
        $this->app->bind(
            VerifiesWalletPin::class,
            VerifyPinAction::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'banking');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileBankLedgerCommand::class,
            ]);
        }

        // Register user wallet listener
        Event::listen(Registered::class, CreateUserWalletListener::class);

        // Register navigation items
        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'Dompet & Saldo',
                route: 'wallet.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
                roles: [],
                order: 10,
                group: 'Keuangan',
                activePattern: 'wallet*',
            );

            $registry->addItem(
                label: 'Transfer Saldo',
                route: 'wallet.transfer',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
                roles: [],
                order: 11,
                group: 'Keuangan',
                activePattern: 'wallet/transfer*',
            );

            $registry->addItem(
                label: 'Mutasi Rekening',
                route: 'wallet.mutasi',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>',
                roles: [],
                order: 12,
                group: 'Keuangan',
                activePattern: 'wallet/mutasi*',
            );

            $registry->addItem(
                label: 'Dashboard Grup Konsolidasi',
                route: 'admin.group-dashboard',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                roles: ['admin'],
                order: 1,
                group: 'Grup & Admin',
                activePattern: 'admin/group-dashboard*',
            );

            $registry->addItem(
                label: 'Ledger Pembukuan',
                route: 'admin.ledger.index',
                icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                roles: ['admin'],
                order: 13,
                group: 'Grup & Admin',
                activePattern: 'admin/ledger*',
            );
        }
    }
}
