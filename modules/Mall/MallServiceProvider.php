<?php

declare(strict_types=1);

namespace Modules\Mall;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Domain\Events\VehicleOwnershipTransferred;
use Modules\Mall\Application\Actions\ValidateParkingAction;
use Modules\Mall\Application\Services\MallLoyaltyLedgerService;
use Modules\Mall\Application\Services\TenantSalesService;
use Modules\Mall\Console\Commands\ApplyPenaltiesCommand;
use Modules\Mall\Console\Commands\AuditBillingCommand;
use Modules\Mall\Console\Commands\AutoDebitCommand;
use Modules\Mall\Console\Commands\ExpirePointsCommand;
use Modules\Mall\Console\Commands\ExpireVouchersCommand;
use Modules\Mall\Console\Commands\GenerateInvoicesCommand;
use Modules\Mall\Console\Commands\GeneratePmOrdersCommand;
use Modules\Mall\Console\Commands\RenewParkingMembersCommand;
use Modules\Mall\Console\Commands\SettleVouchersCommand;
use Modules\Mall\Console\Commands\SimulateFootfallCommand;
use Modules\Mall\Contracts\LoyaltyLedger;
use Modules\Mall\Contracts\ParkingValidator;
use Modules\Mall\Domain\Models\Asset;
use Modules\Mall\Domain\Models\EventBooking;
use Modules\Mall\Domain\Models\EventSpace;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\LoyaltyMember;
use Modules\Mall\Domain\Models\OvertimeRequest;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\PointBatch;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\ReceiptClaim;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\TenantSalesReport;
use Modules\Mall\Domain\Models\Unit;
use Modules\Mall\Domain\Models\UtilityReading;
use Modules\Mall\Domain\Models\Voucher;
use Modules\Mall\Domain\Models\VoucherTemplate;
use Modules\Mall\Domain\Models\WorkOrder;
use Modules\Mall\Domain\Models\Zone;
use Modules\Mall\Listeners\CancelParkingMembershipOnVehicleTransfer;
use Modules\Shared\Application\MenuRegistry;

class MallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantSalesService::class, function ($app) {
            return new TenantSalesService($app->tagged('mall.tenant_sales_provider'));
        });

        // Dipakai POS tenant (mis. Resto) untuk menanggung tarif parkir pelanggan
        $this->app->bind(ParkingValidator::class, ValidateParkingAction::class);

        // Layanan loyalty ledger lintas modul (earn points, apply voucher, redeem points)
        $this->app->bind(
            LoyaltyLedger::class,
            MallLoyaltyLedgerService::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'mall');

        // Sinkronisasi status parkir saat kepemilikan kendaraan berpindah di AutoDex / Store
        Event::listen(
            VehicleOwnershipTransferred::class,
            CancelParkingMembershipOnVehicleTransfer::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateInvoicesCommand::class,
                ApplyPenaltiesCommand::class,
                AuditBillingCommand::class,
                AutoDebitCommand::class,
                RenewParkingMembersCommand::class,
                SimulateFootfallCommand::class,
                ExpirePointsCommand::class,
                SettleVouchersCommand::class,
                ExpireVouchersCommand::class,
                GeneratePmOrdersCommand::class,
            ]);
        }

        if (file_exists(__DIR__.'/routes/web.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        }

        Relation::morphMap([
            'mall_property' => Property::class,
            'mall_zone' => Zone::class,
            'mall_unit' => Unit::class,
            'mall_tenant' => Tenant::class,
            'mall_lease' => Lease::class,
            'mall_invoice' => Invoice::class,
            'mall_invoice_line' => InvoiceLine::class,
            'mall_sales_report' => TenantSalesReport::class,
            'mall_utility_reading' => UtilityReading::class,
            'mall_overtime_request' => OvertimeRequest::class,
            'mall_parking_session' => ParkingSession::class,
            'mall_parking_member' => ParkingMember::class,
            'mall_parking_zone' => ParkingZone::class,
            'mall_loyalty_member' => LoyaltyMember::class,
            'mall_point_batch' => PointBatch::class,
            'mall_receipt_claim' => ReceiptClaim::class,
            'mall_voucher_template' => VoucherTemplate::class,
            'mall_voucher' => Voucher::class,
            'mall_event_space' => EventSpace::class,
            'mall_event_booking' => EventBooking::class,
            'mall_asset' => Asset::class,
            'mall_work_order' => WorkOrder::class,
        ]);

        if ($this->app->bound(MenuRegistry::class)) {
            $registry = $this->app->make(MenuRegistry::class);

            $registry->addItem(
                label: 'Site Plan & Unit',
                route: 'mall.site-plan.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 70,
                group: 'Properti',
                activePattern: 'mall/site-plan*'
            );

            $registry->addItem(
                label: 'Kontrak Sewa (Leasing)',
                route: 'mall.leases.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 71,
                group: 'Properti',
                activePattern: 'mall/leases*'
            );

            $registry->addItem(
                label: 'Tenant Mitra Mall',
                route: 'mall.tenants.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 72,
                group: 'Properti',
                activePattern: 'mall/tenants*'
            );

            $registry->addItem(
                label: 'Direktori Toko Publik',
                route: 'mall.directory.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent', 'customer'],
                order: 73,
                group: 'Properti',
                activePattern: 'mall/directory*'
            );

            $registry->addItem(
                label: 'Tagihan & Piutang Mall',
                route: 'mall.billing.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 74,
                group: 'Properti',
                activePattern: 'mall/billing*'
            );

            $registry->addItem(
                label: 'Pencatatan Meteran Utilitas',
                route: 'mall.utilities.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>',
                roles: ['admin', 'mall_manager', 'technician'],
                order: 75,
                group: 'Properti',
                activePattern: 'mall/utilities*'
            );

            $registry->addItem(
                label: 'Operasional Parkir',
                route: 'mall.parking.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h2.5a2.5 2.5 0 010 5H9m-4 5h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
                roles: ['admin', 'mall_manager'],
                order: 77,
                group: 'Properti',
                activePattern: 'mall/parking'
            );

            $registry->addItem(
                label: 'Gate Masuk & Keluar',
                route: 'mall.parking.gate.entry',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>',
                roles: ['admin', 'mall_manager'],
                order: 78,
                group: 'Properti',
                activePattern: 'mall/parking/gate*'
            );

            $registry->addItem(
                label: 'Langganan Parkir Bulanan',
                route: 'mall.parking.members',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"></path></svg>',
                roles: ['admin', 'mall_manager', 'customer'],
                order: 79,
                group: 'Properti',
                activePattern: 'mall/parking/members*'
            );

            $registry->addItem(
                label: 'Analitik Kunjungan',
                route: 'mall.parking.footfall',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
                roles: ['admin', 'mall_manager'],
                order: 80,
                group: 'Properti',
                activePattern: 'mall/parking/footfall*'
            );

            $registry->addItem(
                label: 'Portal Tenant Mall',
                route: 'mall.portal.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
                roles: ['admin', 'mall_manager', 'tenant'],
                order: 76,
                group: 'Properti',
                activePattern: 'mall/portal*'
            );

            $registry->addItem(
                label: 'Loyalty & Voucher Mall',
                route: 'mall.loyalty.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path></svg>',
                roles: ['admin', 'mall_manager', 'customer'],
                order: 81,
                group: 'Properti',
                activePattern: 'mall/loyalty*'
            );

            $registry->addItem(
                label: 'Sewa Atrium & Event',
                route: 'mall.events.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
                roles: ['admin', 'mall_manager', 'leasing_agent'],
                order: 82,
                group: 'Properti',
                activePattern: 'mall/events*'
            );

            $registry->addItem(
                label: 'Facility Management (SPK)',
                route: 'mall.facilities.index',
                icon: '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>',
                roles: ['admin', 'mall_manager', 'technician'],
                order: 83,
                group: 'Properti',
                activePattern: 'mall/facilities*'
            );
        }
    }
}
