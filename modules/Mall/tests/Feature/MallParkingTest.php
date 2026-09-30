<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Application\Actions\SetPinAction;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Application\Actions\CheckInVehicleAction;
use Modules\Mall\Application\Actions\CheckOutVehicleAction;
use Modules\Mall\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Mall\Application\Actions\RegisterParkingMemberAction;
use Modules\Mall\Application\Actions\RenewParkingMembershipAction;
use Modules\Mall\Application\Actions\ReportLostTicketAction;
use Modules\Mall\Application\Actions\SettleParkingSessionAction;
use Modules\Mall\Application\Actions\ValidateParkingAction;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Exceptions\ParkingZoneFullException;
use Modules\Mall\Domain\Exceptions\TicketAlreadySettledException;
use Modules\Mall\Domain\Exceptions\VehicleAlreadyParkedException;
use Modules\Mall\Domain\Models\FootfallCount;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingTariff;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Tests\TestCase;

class MallParkingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected Property $property;

    protected ParkingTariff $carTariff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BankingSeeder::class);
        $this->seed(MallSeeder::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin.parkir@dutamall.test'],
            ['name' => 'Manajer Parkir', 'role' => 'admin', 'password' => bcrypt('password')]
        );

        $this->customer = User::create([
            'name' => 'Pelanggan Parkir',
            'email' => 'pelanggan.parkir@dutamall.test',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        // PIN dompet memakai mekanisme Banking (hash + lockout), bukan kolom users.pin
        $this->customer->walletAccount('IDR');
        app(SetPinAction::class)->execute($this->customer, '123456');

        $this->property = Property::where('code', 'DM-BJM')->firstOrFail();
        $this->carTariff = ParkingTariff::where('property_id', $this->property->id)
            ->where('vehicle_type', VehicleType::CAR)
            ->firstOrFail();

        app(MallLedgerAccounts::class)->ensureAll();
    }

    // ================= Tarif progresif =================

    public function test_grace_period_makes_short_visit_free(): void
    {
        // Masuk 45 menit lalu keluar: masih di atas grace 15 menit -> tetap bayar 1 jam
        $session = $this->parkFor(45);
        $quote = app(CheckOutVehicleAction::class)->quote($session);

        $this->assertSame(1, $quote['billed_hours']);
        $this->assertSame(5000, $quote['total_fee']);

        // Keluar dalam 10 menit: bebas biaya karena masih dalam masa tenggang
        $quick = $this->parkFor(10, plate: 'DA 0001 AA');
        $quickQuote = app(CheckOutVehicleAction::class)->quote($quick);

        $this->assertSame(0, $quickQuote['total_fee']);
        $this->assertTrue($quickQuote['is_free']);
    }

    public function test_sixty_one_minutes_is_billed_as_two_hours(): void
    {
        $session = $this->parkFor(61);
        $quote = app(CheckOutVehicleAction::class)->quote($session);

        // Jam pertama Rp 5.000 + jam kedua Rp 3.000
        $this->assertSame(2, $quote['billed_hours']);
        $this->assertSame(8000, $quote['total_fee']);
    }

    public function test_long_stay_is_capped_at_daily_maximum(): void
    {
        // 12 jam: 5.000 + 11 x 3.000 = 38.000 -> dibatasi maksimal harian 30.000
        $session = $this->parkFor(12 * 60);
        $quote = app(CheckOutVehicleAction::class)->quote($session);

        $this->assertSame(12, $quote['billed_hours']);
        $this->assertSame($this->carTariff->max_daily_rate, $quote['total_fee']);
        $this->assertSame(30000, $quote['total_fee']);
    }

    public function test_lost_ticket_adds_flat_penalty(): void
    {
        $this->parkFor(120, plate: 'DA 4321 LT');

        $session = app(ReportLostTicketAction::class)->execute(
            propertyId: $this->property->id,
            plateNumber: 'DA 4321 LT',
        );

        // 2 jam = 8.000 + denda tiket hilang 50.000
        $this->assertTrue($session->is_lost_ticket);
        $this->assertSame(50000, $session->penalty_fee);
        $this->assertSame(58000, $session->total_fee);
    }

    // ================= Member =================

    public function test_active_member_parks_for_free_and_expired_member_pays(): void
    {
        $vehicle = $this->makeVehicle('DA 7777 MB');
        $this->topUp($this->customer, 500_000);

        $member = app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
            months: 1,
        );

        $this->assertSame(MemberStatus::ACTIVE, $member->status);

        // Member aktif: gratis walau parkir 5 jam
        $session = $this->parkFor(5 * 60, plate: 'DA 7777 MB');
        $completed = app(CheckOutVehicleAction::class)->execute($session);

        $this->assertSame(0, $completed->total_fee);
        $this->assertSame(ParkingSessionStatus::COMPLETED, $completed->status);
        $this->assertSame(ParkingPaymentMethod::MEMBER_FREE, $completed->payment_method);

        // Setelah keanggotaan kedaluwarsa, tarif normal berlaku kembali
        $member->update([
            'status' => MemberStatus::EXPIRED,
            'end_date' => Carbon::today()->subDay(),
        ]);

        $second = $this->parkFor(5 * 60, plate: 'DA 7777 MB');
        $secondQuote = app(CheckOutVehicleAction::class)->quote($second->fresh('member'));

        $this->assertGreaterThan(0, $secondQuote['total_fee']);
    }

    public function test_membership_registration_debits_wallet_through_ledger(): void
    {
        $vehicle = $this->makeVehicle('DA 2222 WL');
        $this->topUp($this->customer, 500_000);

        $before = $this->walletBalance($this->customer);

        app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
            months: 3,
        );

        $expectedCharge = RegisterParkingMemberAction::DEFAULT_MONTHLY_PRICE * 3;

        $this->assertSame($before - $expectedCharge, $this->walletBalance($this->customer));
        $this->assertSame(
            $expectedCharge,
            (int) LedgerAccount::where('code', RegisterParkingMemberAction::MEMBERSHIP_REVENUE_ACCOUNT)
                ->value('cached_balance')
        );

        $this->assertReconcileClean();
    }

    public function test_membership_auto_renew_expires_when_wallet_is_short(): void
    {
        $vehicle = $this->makeVehicle('DA 3333 RN');
        $this->topUp($this->customer, 200_000);

        $member = app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
            months: 1,
        );

        // Jatuh tempo kemarin dengan saldo yang sudah tidak cukup
        $member->update(['end_date' => Carbon::today()->subDay()]);

        $result = app(RenewParkingMembershipAction::class)->processDue();

        $this->assertSame(0, $result['renewed']);
        $this->assertSame(1, $result['expired']);
        $this->assertSame(MemberStatus::EXPIRED, $member->fresh()->status);
    }

    public function test_membership_auto_renew_succeeds_with_sufficient_balance(): void
    {
        $vehicle = $this->makeVehicle('DA 4444 RN');
        $this->topUp($this->customer, 1_000_000);

        $member = app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
            months: 1,
        );

        $member->update(['end_date' => Carbon::today()->subDay()]);

        $result = app(RenewParkingMembershipAction::class)->processDue();

        $this->assertSame(1, $result['renewed']);
        $this->assertSame(MemberStatus::ACTIVE, $member->fresh()->status);
        $this->assertTrue($member->fresh()->end_date->greaterThan(Carbon::today()));
        $this->assertReconcileClean();
    }

    // ================= Validasi tenant =================

    public function test_tenant_validation_discounts_fare_and_becomes_tenant_receivable(): void
    {
        $session = $this->parkFor(3 * 60, plate: 'DA 9090 VL');

        // RM Sari Ranah menanggung 2 jam pertama bila belanja >= Rp 100.000
        $result = app(ValidateParkingAction::class)->validateTicket(
            ticketNumber: $session->ticket_number,
            tenantExternalRef: 'DM-01',
            spendAmount: 150_000,
        );

        $this->assertSame(2, $result->freeHours);
        $this->assertStringContainsString('2 jam', $result->receiptLine());

        $completed = app(CheckOutVehicleAction::class)->execute($session->fresh());

        // 3 jam = 5.000 + 3.000 + 3.000 = 11.000, ditanggung 2 jam pertama = 8.000
        $this->assertSame(11000, $completed->base_fee);
        $this->assertSame(8000, $completed->discount_amount);
        $this->assertSame(3000, $completed->total_fee);

        // Beban validasi masuk tagihan bulanan tenant sebagai piutang
        $tenant = Tenant::where('external_ref', 'DM-01')->firstOrFail();
        $lease = $tenant->leases()->firstOrFail();

        // Tagihan bulan berjalan sudah diterbitkan seeder; validasi yang belum tertagih
        // ikut pada siklus berikutnya sehingga piutang tidak hilang.
        $invoice = app(GenerateMonthlyInvoicesAction::class)
            ->generateForLease($lease, Carbon::now()->addMonth()->format('Y-m'));

        $line = $invoice->lines()->where('type', InvoiceLineType::PARKING_VALIDATION)->first();

        $this->assertNotNull($line, 'Baris validasi parkir harus muncul di tagihan tenant.');
        $this->assertSame(8000, (int) $line->amount);
        $this->assertSame($invoice->id, $completed->fresh()->validation_invoice_id);
    }

    public function test_validation_is_rejected_when_spend_is_below_minimum(): void
    {
        $session = $this->parkFor(60, plate: 'DA 1111 NV');

        $this->expectException(InvalidParkingTicketException::class);

        app(ValidateParkingAction::class)->validateTicket(
            ticketNumber: $session->ticket_number,
            tenantExternalRef: 'DM-01',
            spendAmount: 50_000,
        );
    }

    public function test_ticket_cannot_be_validated_twice(): void
    {
        $session = $this->parkFor(120, plate: 'DA 1212 DV');
        $validator = app(ValidateParkingAction::class);

        $validator->validateTicket($session->ticket_number, 'DM-01', 150_000);

        $this->expectException(InvalidParkingTicketException::class);

        $validator->validateTicket($session->ticket_number, 'DM-01', 150_000);
    }

    public function test_validation_billed_only_once_across_two_invoice_runs(): void
    {
        $session = $this->parkFor(3 * 60, plate: 'DA 3131 BL');
        app(ValidateParkingAction::class)->validateTicket($session->ticket_number, 'DM-01', 150_000);
        app(CheckOutVehicleAction::class)->execute($session->fresh());

        $tenant = Tenant::where('external_ref', 'DM-01')->firstOrFail();
        $lease = $tenant->leases()->firstOrFail();
        $period = Carbon::now()->addMonth()->format('Y-m');
        $action = app(GenerateMonthlyInvoicesAction::class);

        $first = $action->generateForLease($lease, $period);
        $firstAmount = (int) $first->lines()->where('type', InvoiceLineType::PARKING_VALIDATION)->sum('amount');

        // Jalankan ulang: nominal validasi tidak boleh bertambah dua kali
        $second = $action->generateForLease($lease, $period);
        $secondAmount = (int) $second->lines()->where('type', InvoiceLineType::PARKING_VALIDATION)->sum('amount');

        $this->assertSame(8000, $firstAmount);
        $this->assertSame($firstAmount, $secondAmount);
    }

    // ================= Pembayaran =================

    public function test_cash_payment_records_revenue_and_opens_gate(): void
    {
        $session = $this->parkFor(2 * 60, plate: 'DA 5555 CS');
        $session = app(CheckOutVehicleAction::class)->execute($session);

        $this->assertSame(8000, $session->total_fee);
        $this->assertFalse($session->isPaid());

        $settled = app(SettleParkingSessionAction::class)->execute(
            session: $session,
            method: ParkingPaymentMethod::CASH,
            payer: $this->admin,
            cashTendered: 10_000,
        );

        $this->assertSame(ParkingSessionStatus::COMPLETED, $settled->status);
        $this->assertSame(ParkingPaymentStatus::PAID, $settled->payment_status);
        $this->assertSame(
            8000,
            (int) LedgerAccount::where('code', MallLedgerAccounts::PARKING_REVENUE)->value('cached_balance')
        );

        $this->assertReconcileClean();
    }

    public function test_wallet_payment_charges_customer_and_requires_valid_pin(): void
    {
        $this->topUp($this->customer, 100_000);
        $before = $this->walletBalance($this->customer);

        $session = $this->parkFor(2 * 60, plate: 'DA 6666 WP');
        $session = app(CheckOutVehicleAction::class)->execute($session);

        $settled = app(SettleParkingSessionAction::class)->execute(
            session: $session,
            method: ParkingPaymentMethod::WALLET,
            payer: $this->customer,
            pin: '123456',
        );

        $this->assertSame(ParkingPaymentStatus::PAID, $settled->payment_status);
        $this->assertSame(ParkingPaymentMethod::WALLET, $settled->payment_method);
        $this->assertSame($before - 8000, $this->walletBalance($this->customer));
        $this->assertReconcileClean();
    }

    public function test_insufficient_cash_is_rejected(): void
    {
        $session = $this->parkFor(2 * 60, plate: 'DA 8888 IC');
        $session = app(CheckOutVehicleAction::class)->execute($session);

        $this->expectException(InvalidParkingTicketException::class);

        app(SettleParkingSessionAction::class)->execute(
            session: $session,
            method: ParkingPaymentMethod::CASH,
            payer: $this->admin,
            cashTendered: 5_000,
        );
    }

    public function test_same_ticket_cannot_exit_twice(): void
    {
        $session = $this->parkFor(2 * 60, plate: 'DA 9999 EX');
        $session = app(CheckOutVehicleAction::class)->execute($session);

        app(SettleParkingSessionAction::class)->execute(
            session: $session,
            method: ParkingPaymentMethod::CASH,
            payer: $this->admin,
            cashTendered: 8_000,
        );

        $this->expectException(TicketAlreadySettledException::class);

        app(SettleParkingSessionAction::class)->execute(
            session: $session->fresh(),
            method: ParkingPaymentMethod::CASH,
            payer: $this->admin,
            cashTendered: 8_000,
        );
    }

    public function test_paid_exit_releases_zone_slot(): void
    {
        $zone = ParkingZone::where('property_id', $this->property->id)
            ->where('vehicle_type', VehicleType::CAR)
            ->orderBy('id')
            ->firstOrFail();

        $before = $zone->fresh()->current_occupancy;

        $session = $this->parkFor(2 * 60, plate: 'DA 1010 SL');
        $this->assertSame($before + 1, $zone->fresh()->current_occupancy);

        $session = app(CheckOutVehicleAction::class)->execute($session);
        app(SettleParkingSessionAction::class)->execute(
            session: $session,
            method: ParkingPaymentMethod::CASH,
            payer: $this->admin,
            cashTendered: 8_000,
        );

        $this->assertSame($before, $zone->fresh()->current_occupancy);
    }

    // ================= Kapasitas & duplikasi =================

    public function test_full_zone_rejects_entry(): void
    {
        ParkingZone::where('property_id', $this->property->id)
            ->where('vehicle_type', VehicleType::CAR)
            ->update(['total_capacity' => 1, 'current_occupancy' => 1]);

        $this->expectException(ParkingZoneFullException::class);

        app(CheckInVehicleAction::class)->execute(
            property: $this->property,
            plateNumber: 'DA 1234 FZ',
            vehicleType: VehicleType::CAR,
        );
    }

    public function test_vehicle_already_parked_cannot_enter_again(): void
    {
        $this->parkFor(30, plate: 'DA 2468 DP');

        $this->expectException(VehicleAlreadyParkedException::class);

        app(CheckInVehicleAction::class)->execute(
            property: $this->property,
            plateNumber: 'da 2468 dp',
            vehicleType: VehicleType::CAR,
        );
    }

    public function test_plate_is_matched_to_garage_vehicle(): void
    {
        $vehicle = $this->makeVehicle('DA 1357 GR');

        $session = app(CheckInVehicleAction::class)->execute(
            property: $this->property,
            plateNumber: 'da 1357 gr',
            vehicleType: VehicleType::CAR,
        );

        $this->assertSame('DA 1357 GR', $session->plate_number);
        $this->assertSame($vehicle->id, $session->vehicle_id);
    }

    // ================= HTTP & footfall =================

    public function test_gate_screens_and_occupancy_endpoint_are_reachable(): void
    {
        $this->actingAs($this->admin)
            ->get(route('mall.parking.gate.entry'))
            ->assertOk()
            ->assertSee('Terbitkan Tiket Masuk');

        $this->actingAs($this->admin)
            ->get(route('mall.parking.index'))
            ->assertOk()
            ->assertSee('Okupansi');

        $response = $this->actingAs($this->admin)
            ->getJson(route('mall.parking.occupancy', ['property_id' => $this->property->id]));

        $response->assertOk()
            ->assertJsonStructure(['property', 'updated_at', 'zones', 'total_capacity', 'total_occupied']);
    }

    public function test_gate_entry_via_http_issues_ticket(): void
    {
        $response = $this->actingAs($this->admin)->post(route('mall.parking.gate.check-in'), [
            'property_id' => $this->property->id,
            'plate_number' => 'DA 2020 HT',
            'vehicle_type' => VehicleType::CAR->value,
            'entry_gate' => 'Gate Masuk 1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('mall_parking_sessions', [
            'plate_number' => 'DA 2020 HT',
            'status' => ParkingSessionStatus::ACTIVE->value,
        ]);
    }

    public function test_customer_cannot_open_gate_screen(): void
    {
        $this->actingAs($this->customer)
            ->post(route('mall.parking.gate.check-in'), [
                'property_id' => $this->property->id,
                'plate_number' => 'DA 3030 NO',
                'vehicle_type' => VehicleType::CAR->value,
            ])
            ->assertForbidden();
    }

    public function test_customer_only_sees_own_membership(): void
    {
        $other = User::create([
            'name' => 'Pelanggan Lain',
            'email' => 'lain.parkir@dutamall.test',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $vehicle = $this->makeVehicle('DA 5151 OW');
        $this->topUp($this->customer, 500_000);

        $member = app(RegisterParkingMemberAction::class)->execute(
            property: $this->property,
            user: $this->customer,
            vehicle: $vehicle,
            pin: '123456',
        );

        $this->actingAs($this->customer)
            ->get(route('mall.parking.members'))
            ->assertOk()
            ->assertSee($member->member_number);

        $this->actingAs($other)
            ->get(route('mall.parking.members'))
            ->assertOk()
            ->assertDontSee($member->member_number);
    }

    public function test_footfall_command_fills_analytics(): void
    {
        $exitCode = Artisan::call('mall:simulate-footfall', ['--days' => 7]);
        $this->assertSame(0, $exitCode);

        // 7 hari x 13 jam operasional x 3 gate pada jendela yang diminta.
        // Command bersifat upsert sehingga data seeder 30 hari tidak terduplikasi.
        $windowRows = FootfallCount::where('property_id', $this->property->id)
            ->whereBetween('date', [
                Carbon::today()->subDays(6)->toDateString(),
                Carbon::today()->toDateString(),
            ])
            ->count();

        $this->assertSame(7 * 13 * 3, $windowRows);

        $this->actingAs($this->admin)
            ->get(route('mall.parking.footfall', ['days' => 7]))
            ->assertOk()
            ->assertSee('Total Kunjungan');
    }

    public function test_many_sessions_keep_dashboard_within_query_budget(): void
    {
        $zone = ParkingZone::where('property_id', $this->property->id)
            ->where('vehicle_type', VehicleType::CAR)
            ->orderBy('id')
            ->firstOrFail();

        // Sisipkan 300 sesi selesai secara batch untuk menguji biaya query dashboard
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $entry = Carbon::now()->subHours(5)->addMinutes($i);
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'ticket_number' => sprintf('TKT-BULK-%05d', $i),
                'property_id' => $this->property->id,
                'parking_zone_id' => $zone->id,
                'plate_number' => sprintf('DA %04d BK', $i),
                'vehicle_type' => VehicleType::CAR->value,
                'entry_gate' => 'Gate Masuk 1',
                'entry_time' => $entry,
                'exit_time' => $entry->copy()->addHours(2),
                'duration_minutes' => 120,
                'base_fee' => 8000,
                'penalty_fee' => 0,
                'discount_amount' => 0,
                'total_fee' => 8000,
                'payment_status' => ParkingPaymentStatus::PAID->value,
                'paid_at' => $entry->copy()->addHours(2),
                'status' => ParkingSessionStatus::COMPLETED->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ParkingSession::insert($rows);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($this->admin)
            ->get(route('mall.parking.index', ['property_id' => $this->property->id]))
            ->assertOk();

        $this->assertLessThanOrEqual(
            30,
            $queries,
            "Dashboard parkir memakai {$queries} query, melebihi anggaran 30 query."
        );
    }

    // ================= Helper =================

    private function parkFor(int $minutes, string $plate = 'DA 1234 XY'): ParkingSession
    {
        return app(CheckInVehicleAction::class)->execute(
            property: $this->property,
            plateNumber: $plate,
            vehicleType: VehicleType::CAR,
            entryTime: Carbon::now()->subMinutes($minutes),
        );
    }

    private function makeVehicle(string $plate): Vehicle
    {
        return Vehicle::create([
            'user_id' => $this->customer->id,
            'car_id' => Car::query()->value('id'),
            'plate_number' => strtoupper($plate),
            'color' => 'Silver',
            'odometer_km' => 10_000,
            'acquired_at' => now(),
            'status' => 'active',
        ]);
    }

    private function topUp(User $user, int $amount): void
    {
        $user->walletAccount('IDR');
        app(TopUpAction::class)->execute($user, (string) $amount, 'test_topup_'.$user->id.'_'.uniqid());
    }

    private function walletBalance(User $user): int
    {
        return BigDecimal::of($user->walletAccount('IDR')->fresh()->cached_balance ?: '0')->toInt();
    }

    private function assertReconcileClean(): void
    {
        $this->assertSame(0, Artisan::call('bank:reconcile'), 'Rekonsiliasi ledger tidak bersih.');
    }
}
