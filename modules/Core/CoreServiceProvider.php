<?php

declare(strict_types=1);

namespace Modules\Core;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\AutoServe\Domain\Events\BookingCompleted;
use Modules\Core\Application\Actions\AcquireVehicleAction;
use Modules\Core\Application\Actions\TransferVehicleOwnershipAction;
use Modules\Core\Application\Listeners\NotifyBookingCompleted;
use Modules\Core\Application\Listeners\NotifyPaymentCaptured;
use Modules\Core\Application\Listeners\NotifyPaymentRefunded;
use Modules\Core\Application\Listeners\RecordBookingCompletedPassportEvent;
use Modules\Core\Application\Listeners\RecordVehicleAcquiredPassportEvent;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\AuditTrailService;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Core\Application\Services\RbacService;
use Modules\Core\Console\Commands\ProcessOutboxCommand;
use Modules\Core\Console\Commands\SuperHealthCheckCommand;
use Modules\Core\Console\Commands\VerifyPassportsCommand;
use Modules\Core\Contracts\AcquiresVehicle;
use Modules\Core\Contracts\AuditTrailInterface;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\Core\Contracts\TransfersVehicleOwnership;
use Modules\Core\Domain\Events\VehicleAcquired;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Payment\Domain\Events\PaymentCaptured;
use Modules\Payment\Domain\Events\PaymentRefunded;
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

        $this->app->bind(
            AuditTrailInterface::class,
            AuditTrailService::class
        );

        $this->app->bind(
            OutboxBusInterface::class,
            OutboxBusService::class
        );

        // Platform services — singletons so they can be injected anywhere
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(ActivityLogger::class);
        $this->app->singleton(RbacService::class);
        $this->app->singleton(AuditTrailService::class);
        $this->app->singleton(OutboxBusService::class);
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

        // === Gate: RBAC permission-based authorization ===
        // Admin bypasses all gates; otherwise check RBAC permissions
        Gate::before(function ($user, $ability) {
            if ($user->role === 'admin' || $user->hasRbacRole('admin')) {
                return true;
            }

            // Check if the user has the permission through RBAC
            if ($user->hasRbacPermission($ability)) {
                return true;
            }

            // Return null to let the policy/gate decide
            return null;
        });

        // Register menu items
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

        $registry->addItem(
            label: 'Kesehatan Sistem',
            route: 'admin.health.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
            roles: ['admin'],
            order: 99,
            group: 'Grup & Admin',
            activePattern: 'admin/health*',
        );

        $registry->addItem(
            label: 'Roles & Permissions',
            route: 'admin.rbac.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            roles: ['admin'],
            order: 98,
            group: 'Grup & Admin',
            activePattern: 'admin/rbac*',
        );

        $registry->addItem(
            label: 'Audit Trail',
            route: 'admin.audit-logs.index',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            roles: ['admin'],
            order: 97,
            group: 'Grup & Admin',
            activePattern: 'admin/audit-logs*',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                VerifyPassportsCommand::class,
                SuperHealthCheckCommand::class,
                ProcessOutboxCommand::class,
            ]);
        }

        // === Event Listeners ===

        // Passport Hash-Chain
        Event::listen(VehicleAcquired::class, RecordVehicleAcquiredPassportEvent::class);
        Event::listen(BookingCompleted::class, RecordBookingCompletedPassportEvent::class);

        // Platform Notifications + Activity Log
        Event::listen(BookingCompleted::class, NotifyBookingCompleted::class);
        Event::listen(PaymentCaptured::class, NotifyPaymentCaptured::class);
        Event::listen(PaymentRefunded::class, NotifyPaymentRefunded::class);
    }
}
