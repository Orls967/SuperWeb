<?php

declare(strict_types=1);

namespace Modules\Core;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Application\Actions\TransferVehicleOwnershipAction;
use Modules\Core\Application\Listeners\RecordBookingCompletedPassportEvent;
use Modules\Core\Application\Listeners\RecordVehicleAcquiredPassportEvent;
use Modules\Core\Console\Commands\VerifyPassportsCommand;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Contracts\TransfersVehicleOwnership;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Shared\Application\MenuRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AcquiresVehicle::class,
            AcquireVehicleAction::class
        );

        $this->app->bind(
            TransfersVehicleOwnership::class,
            TransferVehicleOwnershipAction::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'core');

        Relation::morphMap([
            'core_vehicle' => Vehicle::class,
        ]);

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        // Register default Core menu item
        $registry = $this->app->make(MenuRegistry::class);
        $registry->addItem(
            label: 'Dashboard',
            route: 'dashboard',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
            roles: [],
            order: 1,
            group: 'Core',
            activePattern: 'dashboard',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyPassportsCommand::class,
            ]);
        }

        // Register Passport Hash-Chain Event Listeners
        Event::listen(
            VehicleAcquired::class,
            RecordVehicleAcquiredPassportEvent::class
        );
        Event::listen(
            BookingCompleted::class,
            RecordBookingCompletedPassportEvent::class
        );
    }
}
