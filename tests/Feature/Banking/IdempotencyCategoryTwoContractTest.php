<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AutoServe\Application\Actions\ApproveEstimateAction;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\AutoServe\Domain\Models\Service;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Core\Application\Services\OutboxBusService;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Finance\Application\Actions\PayInstallmentAction;
use Modules\Finance\Domain\Models\Installment;
use Modules\Finance\Domain\Models\Loan;
use Modules\Mall\Application\Actions\SettleParkingSessionAction;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;
use Modules\Payment\Application\Services\PaymentGatewayService;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Proptech\Application\Services\ProptechService;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Shared\Domain\ValueObjects\Money;
use Tests\TestCase;

class IdempotencyCategoryTwoContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    private function makeUserWithWallet(int $balance = 50_000_000, string $pin = '123456'): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        app(SetPinAction::class)->execute($user, $pin);
        if ($balance > 0) {
            app(TopUpAction::class)->execute($user, $balance, 'idem_cat2_'.Str::uuid());
        }

        return $user;
    }

    public function test_category_2_payment_gateway_two_distinct_refunds_create_two_postings(): void
    {
        $user = $this->makeUserWithWallet(5_000_000);
        $service = app(PaymentGatewayService::class);

        $srv = Service::firstOrCreate(
            ['name' => 'General Service Cat 2'],
            ['price' => 100000, 'description' => 'Servis umum']
        );

        $b1 = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $user->id,
            'service_id' => $srv->id,
            'plate_number' => 'B 1234 PAY',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'complaint' => 'Servis rutin',
            'booking_date' => now()->toDateString(),
            'booking_time' => '10:00',
            'status' => 'completed',
            'service_cost' => 1_000_000,
            'sparepart_cost' => 0,
            'grand_total' => 1_000_000,
            'payment_status' => 'unpaid',
        ]);

        $b2 = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $user->id,
            'service_id' => $srv->id,
            'plate_number' => 'B 5678 PAY',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Innova',
            'complaint' => 'Servis rutin 2',
            'booking_date' => now()->toDateString(),
            'booking_time' => '11:00',
            'status' => 'completed',
            'service_cost' => 1_000_000,
            'sparepart_cost' => 0,
            'grand_total' => 1_000_000,
            'payment_status' => 'unpaid',
        ]);

        // 1. Buat intent yang di-charge
        $intent1 = $service->charge($b1, 'test_charge_1');
        $intent2 = $service->charge($b2, 'test_charge_2');

        // 2. Refund kejadian A dan refund kejadian B memakai refundId berbeda (Category ii)
        $service->refund($intent1, 'REF-EVENT-001', Money::fromIdr(500_000), 'Refund A');
        $service->refund($intent2, 'REF-EVENT-002', Money::fromIdr(500_000), 'Refund B');

        $this->assertTrue(LedgerTransaction::where('idempotency_key', 'refund:REF-EVENT-001')->exists());
        $this->assertTrue(LedgerTransaction::where('idempotency_key', 'refund:REF-EVENT-002')->exists());
    }

    public function test_category_2_finance_two_distinct_installments_create_two_postings(): void
    {
        $user = $this->makeUserWithWallet(5_000_000);
        $asset = CryptoAsset::firstOrCreate(
            ['symbol' => 'BTC'],
            ['name' => 'Bitcoin', 'decimals' => 8, 'volatility' => 0.03, 'is_active' => true]
        );

        $loan = Loan::create([
            'user_id' => $user->id,
            'principal' => 3_000_000,
            'down_payment' => 500_000,
            'tenor_months' => 3,
            'interest_rate_annual' => 0.08,
            'collateral_asset_id' => $asset->id,
            'collateral_qty' => '0.50000000',
            'ltv_at_open' => 20.0,
            'outstanding_principal' => 3_000_000,
            'status' => 'active',
        ]);

        $inst1 = Installment::create([
            'loan_id' => $loan->id,
            'sequence' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'principal_part' => 1_000_000,
            'interest_part' => 50_000,
            'amount' => 1_050_000,
            'penalty' => 0,
            'status' => 'scheduled',
        ]);

        $inst2 = Installment::create([
            'loan_id' => $loan->id,
            'sequence' => 2,
            'due_date' => now()->addMonths(2)->toDateString(),
            'principal_part' => 1_000_000,
            'interest_part' => 50_000,
            'amount' => 1_050_000,
            'penalty' => 0,
            'status' => 'scheduled',
        ]);

        $inst3 = Installment::create([
            'loan_id' => $loan->id,
            'sequence' => 3,
            'due_date' => now()->addMonths(3)->toDateString(),
            'principal_part' => 1_000_000,
            'interest_part' => 50_000,
            'amount' => 1_050_000,
            'penalty' => 0,
            'status' => 'scheduled',
        ]);

        $payAction = app(PayInstallmentAction::class);

        // Dua cicilan berbeda menghasilkan dua posting berbeda dengan kunci entitas masing-masing (Category ii)
        $payAction->execute($inst1);
        $payAction->execute($inst2);

        $key1 = 'loan_inst_'.$loan->uuid.'_1';
        $key2 = 'loan_inst_'.$loan->uuid.'_2';

        $this->assertTrue(LedgerTransaction::where('idempotency_key', $key1)->exists());
        $this->assertTrue(LedgerTransaction::where('idempotency_key', $key2)->exists());
    }

    public function test_category_2_mall_parking_settlement_two_distinct_sessions_create_two_postings(): void
    {
        $this->seed(MallSeeder::class);
        $user = $this->makeUserWithWallet(100_000);

        $property = Property::firstOrFail();
        $zone = ParkingZone::firstOrFail();

        $session1 = ParkingSession::create([
            'property_id' => $property->id,
            'parking_zone_id' => $zone->id,
            'ticket_number' => 'TKT-IDEM-001',
            'plate_number' => 'B 1234 IDM',
            'vehicle_type' => VehicleType::CAR,
            'entry_time' => now()->subHours(2),
            'exit_time' => now(),
            'total_fee' => 8000,
            'status' => ParkingSessionStatus::ACTIVE,
            'is_paid' => false,
        ]);

        $session2 = ParkingSession::create([
            'property_id' => $property->id,
            'parking_zone_id' => $zone->id,
            'ticket_number' => 'TKT-IDEM-002',
            'plate_number' => 'B 5678 IDM',
            'vehicle_type' => VehicleType::CAR,
            'entry_time' => now()->subHours(2),
            'exit_time' => now(),
            'total_fee' => 8000,
            'status' => ParkingSessionStatus::ACTIVE,
            'is_paid' => false,
        ]);

        $action = app(SettleParkingSessionAction::class);

        $action->execute($session1, ParkingPaymentMethod::WALLET, $user, '123456');
        $action->execute($session2, ParkingPaymentMethod::WALLET, $user, '123456');

        $key1 = 'mall:parking:settle:'.$session1->uuid.':wallet';
        $key2 = 'mall:parking:settle:'.$session2->uuid.':wallet';

        $this->assertTrue(PaymentIntent::where('idempotency_key', $key1)->exists());
        $this->assertTrue(PaymentIntent::where('idempotency_key', $key2)->exists());
    }

    public function test_category_2_core_outbox_bus_service_two_events_create_two_entries(): void
    {
        $bus = app(OutboxBusService::class);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $msg1 = $bus->record('UserRegistered', ['email' => $user1->email], aggregate: $user1);
        $msg2 = $bus->record('UserRegistered', ['email' => $user2->email], aggregate: $user2);

        $this->assertNotSame($msg1->idempotency_key, $msg2->idempotency_key);
        $this->assertNotSame($msg1->id, $msg2->id);
    }

    public function test_category_2_proptech_two_distinct_readings_create_two_entries(): void
    {
        $service = app(ProptechService::class);
        $zone = BuildingZone::create([
            'zone_code' => 'ZN-IDEM-01',
            'building_code' => 'BLD-01',
            'name' => 'Zone Test Proptech',
            'floor_level' => 'L1',
            'area_sqm' => 100.0,
            'status' => 'ACTIVE',
        ]);

        $t1 = Carbon::parse('2026-10-10 10:00:00');
        $t2 = Carbon::parse('2026-10-10 10:05:00');

        $reading1 = $service->ingestSensorReading($zone, 'SN-01', 'TEMP', 24.5, $t1);
        $reading2 = $service->ingestSensorReading($zone, 'SN-01', 'TEMP', 25.0, $t2);

        $this->assertNotSame($reading1->idempotency_key, $reading2->idempotency_key);
        $this->assertNotSame($reading1->id, $reading2->id);
    }

    public function test_category_2_autoserve_two_distinct_estimates_create_two_hold_intents(): void
    {
        $user = $this->makeUserWithWallet(5_000_000);
        $srv = Service::firstOrCreate(
            ['name' => 'Service Cat 2 Est'],
            ['price' => 100000, 'description' => 'Servis umum']
        );

        $b1 = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $user->id,
            'service_id' => $srv->id,
            'plate_number' => 'B 1111 EST',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Avanza',
            'complaint' => 'Est 1',
            'booking_date' => now()->toDateString(),
            'booking_time' => '10:00',
            'status' => 'confirmed',
            'service_cost' => 500_000,
            'sparepart_cost' => 0,
            'grand_total' => 500_000,
            'payment_status' => 'unpaid',
        ]);

        $b2 = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => $user->id,
            'service_id' => $srv->id,
            'plate_number' => 'B 2222 EST',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Innova',
            'complaint' => 'Est 2',
            'booking_date' => now()->toDateString(),
            'booking_time' => '11:00',
            'status' => 'confirmed',
            'service_cost' => 500_000,
            'sparepart_cost' => 0,
            'grand_total' => 500_000,
            'payment_status' => 'unpaid',
        ]);

        $est1 = Estimate::create([
            'uuid' => (string) Str::uuid(),
            'booking_id' => $b1->id,
            'status' => EstimateStatus::Sent,
            'items' => [['type' => 'service', 'name' => 'Tune Up', 'price' => 500_000, 'qty' => 1]],
            'subtotal' => 500_000,
            'total' => 500_000,
        ]);

        $est2 = Estimate::create([
            'uuid' => (string) Str::uuid(),
            'booking_id' => $b2->id,
            'status' => EstimateStatus::Sent,
            'items' => [['type' => 'service', 'name' => 'Tune Up', 'price' => 500_000, 'qty' => 1]],
            'subtotal' => 500_000,
            'total' => 500_000,
        ]);

        $action = app(ApproveEstimateAction::class);
        $action->execute($est1, $user, '123456');
        $action->execute($est2, $user, '123456');

        $key1 = 'estimate_hold_'.$est1->uuid;
        $key2 = 'estimate_hold_'.$est2->uuid;

        $this->assertTrue(PaymentIntent::where('idempotency_key', $key1)->exists());
        $this->assertTrue(PaymentIntent::where('idempotency_key', $key2)->exists());
    }
}
