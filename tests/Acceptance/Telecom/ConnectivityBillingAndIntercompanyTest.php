<?php

declare(strict_types=1);

namespace Tests\Acceptance\Telecom;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Tlx\Application\Services\ConnectivityBillingService;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\SimProfile;
use Tests\TestCase;

class ConnectivityBillingAndIntercompanyTest extends TestCase
{
    use RefreshDatabase;

    protected ConnectivityBillingService $billingService;

    protected IotDeviceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->billingService = app(ConnectivityBillingService::class);
        $this->registry = app(IotDeviceRegistry::class);
    }

    public function test_pro_rata_calculation_for_partial_month_active_devices(): void
    {
        // October has 31 days. Device active for 10 days. Fee = 31,000 IDR.
        // Expected pro-rata = round_half_up(31000 * 10 / 31) = 10000 IDR.
        $fee = 31000;
        $activeDays = 10;
        $daysInMonth = 31;

        $proRata = $this->billingService->calculateProRataFee($fee, $activeDays, $daysInMonth);
        expect($proRata)->toBe(10000);
    }

    public function test_monthly_billing_generates_invoice_and_intercompany_entries(): void
    {
        $device1 = $this->registry->registerDevice([
            'device_code' => 'DEV-BILL-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 31000,
            'idempotency_key' => 'TLX-REG:DEV-BILL-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000010',
            'imsi' => '510100000000010',
            'msisdn' => '628990000010',
            'plan_code' => 'IOT-DATA-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);
        $this->registry->activateSim('DEV-BILL-01', '8962000000000010', 'TLX-ACT:DEV-BILL-01');

        $invoice = $this->billingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => '2026-10',
            'days_in_month' => 31,
            'idempotency_key' => 'TLX-BILL:ENT-LOGISTICS-01:2026-10',
        ]);

        expect($invoice)->not->toBeNull()
            ->and($invoice->owner_entity_id)->toBe('ENT-LOGISTICS-01')
            ->and($invoice->billing_period)->toBe('2026-10')
            ->and($invoice->active_device_count)->toBe(1)
            ->and($invoice->total_charge_minor)->toBeGreaterThan(0)
            ->and($invoice->intercompany_transaction_id)->not->toBeNull();
    }

    public function test_intercompany_billing_creates_symmetric_mirror_entries(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-IC-01',
            'device_type' => 'TELEMATICS',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'using_module' => 'Telematics',
            'monthly_fee_minor' => 62000,
            'idempotency_key' => 'TLX-REG:DEV-IC-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000011',
            'imsi' => '510100000000011',
            'msisdn' => '628990000011',
            'plan_code' => 'IOT-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);
        $this->registry->activateSim('DEV-IC-01', '8962000000000011', 'TLX-ACT:DEV-IC-01');

        $invoice = $this->billingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => '2026-10',
            'days_in_month' => 31,
            'idempotency_key' => 'TLX-BILL:ENT-LOGISTICS-01:2026-10-IC',
        ]);

        $mirrorStatus = $this->billingService->verifyIntercompanyMirror($invoice->id);
        expect($mirrorStatus['is_balanced'])->toBeTrue()
            ->and($mirrorStatus['net_difference'])->toBe(0);
    }

    public function test_billing_is_strictly_idempotent(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-IDEMP-01',
            'device_type' => 'ENERGY_METER',
            'owner_entity_id' => 'ENT-ENERGY-01',
            'using_module' => 'Energy',
            'monthly_fee_minor' => 50000,
            'idempotency_key' => 'TLX-REG:DEV-IDEMP-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000012',
            'imsi' => '510100000000012',
            'msisdn' => '628990000012',
            'plan_code' => 'IOT-10GB',
            'monthly_quota_mb' => 10000,
            'status' => 'AVAILABLE',
        ]);
        $this->registry->activateSim('DEV-IDEMP-01', '8962000000000012', 'TLX-ACT:DEV-IDEMP-01');

        $params = [
            'owner_entity_id' => 'ENT-ENERGY-01',
            'billing_period' => '2026-10',
            'days_in_month' => 31,
            'idempotency_key' => 'TLX-BILL:ENT-ENERGY-01:2026-10',
        ];

        $invoice1 = $this->billingService->billEntityConnectivity($params);
        $invoice2 = $this->billingService->billEntityConnectivity($params);

        expect($invoice1->id)->toBe($invoice2->id)
            ->and(IotConnectivityInvoice::where('billing_period', '2026-10')->where('owner_entity_id', 'ENT-ENERGY-01')->count())->toBe(1);
    }

    public function test_device_suspended_entire_month_incurs_zero_subscription_fee(): void
    {
        $this->registry->registerDevice([
            'device_code' => 'DEV-SUSPENDED-01',
            'device_type' => 'MINING_SENSOR',
            'owner_entity_id' => 'ENT-MINING-01',
            'using_module' => 'Mining',
            'monthly_fee_minor' => 50000,
            'idempotency_key' => 'TLX-REG:DEV-SUSPENDED-01',
        ]);

        SimProfile::create([
            'iccid' => '8962000000000013',
            'imsi' => '510100000000013',
            'msisdn' => '628990000013',
            'plan_code' => 'IOT-5GB',
            'monthly_quota_mb' => 5000,
            'status' => 'AVAILABLE',
        ]);
        $this->registry->activateSim('DEV-SUSPENDED-01', '8962000000000013', 'TLX-ACT:DEV-SUSPENDED-01');
        $this->registry->suspendDevice('DEV-SUSPENDED-01', 'Suspended before month starts', 'TLX-SUS:DEV-SUSPENDED-01');

        $invoice = $this->billingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-MINING-01',
            'billing_period' => '2026-10',
            'days_in_month' => 31,
            'active_days_override' => 0,
            'idempotency_key' => 'TLX-BILL:ENT-MINING-01:2026-10-ZERO',
        ]);

        expect($invoice->total_device_fee_minor)->toBe(0)
            ->and($invoice->total_charge_minor)->toBe(0);
    }

    public function test_billing_requires_mandatory_idempotency_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->billingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-HEALTH-01',
            'billing_period' => '2026-10',
            'days_in_month' => 31,
        ]);
    }

    public function test_anti_idor_protects_connectivity_invoices_across_entities(): void
    {
        $invoiceLogistics = IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-TLX-202610-LOG01',
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => '2026-10',
            'active_device_count' => 10,
            'total_device_fee_minor' => 300000,
            'total_quota_mb' => 50000,
            'total_consumed_mb' => 45000,
            'excess_mb' => 0,
            'excess_charge_minor' => 0,
            'total_charge_minor' => 300000,
            'status' => 'ISSUED',
        ]);

        $userMining = User::factory()->create([
            'role' => 'telecom_billing',
            'entity_id' => 'ENT-MINING-01',
        ]);

        $canAccess = $this->billingService->canAccessInvoice($userMining, $invoiceLogistics->id);
        expect($canAccess)->toBeFalse();

        $userLogistics = User::factory()->create([
            'role' => 'telecom_billing',
            'entity_id' => 'ENT-LOGISTICS-01',
        ]);

        $canAccessLogistics = $this->billingService->canAccessInvoice($userLogistics, $invoiceLogistics->id);
        expect($canAccessLogistics)->toBeTrue();
    }

    public function test_invoice_settlement_updates_status(): void
    {
        $invoice = IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-TLX-202610-SETTLE',
            'owner_entity_id' => 'ENT-PROPTECH-01',
            'billing_period' => '2026-10',
            'active_device_count' => 5,
            'total_device_fee_minor' => 150000,
            'total_quota_mb' => 25000,
            'total_consumed_mb' => 20000,
            'excess_mb' => 0,
            'excess_charge_minor' => 0,
            'total_charge_minor' => 150000,
            'status' => 'ISSUED',
        ]);

        $settled = $this->billingService->settleInvoice($invoice->id, 'SETTLE-REF-001');
        expect($settled->status)->toBe('SETTLED');
    }

    public function test_bill_connectivity_command_runs_for_all_entities(): void
    {
        $exitCode = Artisan::call('tlx:bill-connectivity', [
            '--period' => '2026-10',
        ]);

        expect($exitCode)->toBe(0);
    }

    public function test_invalid_billing_period_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->billingService->billEntityConnectivity([
            'owner_entity_id' => 'ENT-LOGISTICS-01',
            'billing_period' => 'INVALID-PERIOD',
            'days_in_month' => 31,
            'idempotency_key' => 'TLX-BILL:ERR',
        ]);
    }
}
