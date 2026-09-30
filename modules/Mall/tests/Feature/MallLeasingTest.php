<?php

declare(strict_types=1);

namespace Modules\Mall\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Application\Actions\TopUpAction;
use Modules\Banking\database\seeders\BankingSeeder;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Mall\Application\Actions\ActivateLeaseAction;
use Modules\Mall\Application\Actions\CreateLeaseAction;
use Modules\Mall\Application\Actions\RenewLeaseAction;
use Modules\Mall\Application\Actions\TerminateLeaseAction;
use Modules\Mall\Application\Queries\OccupancyQuery;
use Modules\Mall\database\seeders\MallSeeder;
use Modules\Mall\Domain\Enums\DepositStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Enums\TenantCategory;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Exceptions\UnitAlreadyLeasedException;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;
use Tests\TestCase;

class MallLeasingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $tenantUser;

    protected Property $property;

    protected Tenant $tenant;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([BankingSeeder::class, MallSeeder::class]);

        $this->admin = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);

        $this->tenantUser = User::factory()->create([
            'role' => 'customer',
            'name' => 'Mitra Ritel Indonesia',
            'email' => 'mitra.mall@autoserve.test',
        ]);

        $this->property = Property::where('code', 'DM-BJM')->firstOrFail();
        $this->unit = Unit::where('property_id', $this->property->id)->where('status', UnitStatus::AVAILABLE)->firstOrFail();

        $this->tenant = Tenant::create([
            'brand_name' => 'Gramedia Books & Stationery',
            'company_name' => 'PT Gramedia Asri Media',
            'category' => TenantCategory::OTHER,
            'pic_name' => 'Hendrawan',
            'pic_phone' => '081288887777',
            'pic_email' => 'hendrawan@gramedia.test',
            'user_id' => $this->tenantUser->id,
            'is_active' => true,
        ]);
    }

    public function test_overlapping_lease_on_same_unit_is_rejected_with_exception(): void
    {
        $createLease = app(CreateLeaseAction::class);

        // 1. Buat lease pertama: 1 Jan 2027 s/d 31 Des 2027
        $lease1 = $createLease->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2027-01-01',
            endDate: '2027-12-31'
        );

        $this->assertNotNull($lease1->id);
        $this->assertEquals(LeaseStatus::DRAFT, $lease1->status);

        // 2. Coba buat lease kedua yang tumpang tindih (1 Jun 2027 s/d 31 Mei 2028)
        $this->expectException(UnitAlreadyLeasedException::class);

        $createLease->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2027-06-01',
            endDate: '2028-05-31'
        );
    }

    public function test_non_overlapping_lease_on_different_period_is_allowed(): void
    {
        $createLease = app(CreateLeaseAction::class);

        // Lease 1: 1 Jan 2027 s/d 31 Des 2027
        $createLease->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2027-01-01',
            endDate: '2027-12-31'
        );

        // Lease 2: 1 Jan 2028 s/d 31 Des 2028 (Tepat setelah Lease 1, tidak bertabrakan)
        $lease2 = $createLease->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2028-01-01',
            endDate: '2028-12-31'
        );

        $this->assertNotNull($lease2->id);
    }

    public function test_lease_activation_deducts_security_deposit_to_liability_account(): void
    {
        // 1. Top up dompet tenant Rp50.000.000 (maksimal per transaksi top up)
        app(TopUpAction::class)->execute($this->tenantUser, '50000000', 'topup_tenant_lease');

        $walletAcc = $this->tenantUser->walletAccount('IDR');
        $initialBalance = (int) $walletAcc->cached_balance;

        // 2. Buat draft sewa: sewa bulanan Rp10.000.000, deposit 3 bulan = Rp30.000.000
        $lease = app(CreateLeaseAction::class)->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2026-10-01',
            endDate: '2027-09-30',
            baseMonthlyRent: 10000000,
            serviceChargeMonthly: 2000000,
            securityDepositMonths: 3
        );

        $this->assertEquals(30000000, $lease->security_deposit_amount);
        $this->assertEquals(DepositStatus::UNPAID, $lease->deposit_status);
        $this->assertEquals(LeaseStatus::DRAFT, $lease->status);

        // 3. Aktivasi kontrak sewa & bayar deposit
        $activateAction = app(ActivateLeaseAction::class);
        $activeLease = $activateAction->handle($lease, $this->tenantUser);

        $this->assertEquals(LeaseStatus::ACTIVE, $activeLease->status);
        $this->assertEquals(DepositStatus::HELD, $activeLease->deposit_status);
        $this->assertNotNull($activeLease->activated_at);

        // Unit berubah menjadi LEASED
        $this->assertEquals(UnitStatus::LEASED, $this->unit->fresh()->status);

        // 4. Periksa saldo dompet tenant berkurang Rp30.000.000
        $walletAcc->refresh();
        $this->assertEquals($initialBalance - 30000000, (int) $walletAcc->cached_balance);

        // 5. Periksa akun liability deposit mall menampung titipan Rp30.000.000
        $liabilityAcc = LedgerAccount::where('code', 'liability:mall:tenant_deposit:IDR')->firstOrFail();
        $this->assertEquals(30000000, (int) $liabilityAcc->cached_balance);
    }

    public function test_lease_termination_deducts_outstanding_and_refunds_deposit_balance(): void
    {
        // 1. Top up tenant Rp50.000.000
        app(TopUpAction::class)->execute($this->tenantUser, '50000000', 'topup_tenant_term');

        $lease = app(CreateLeaseAction::class)->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2026-10-01',
            endDate: '2027-09-30',
            baseMonthlyRent: 5000000,
            securityDepositMonths: 3 // Rp15.000.000 deposit
        );

        $lease = app(ActivateLeaseAction::class)->handle($lease, $this->tenantUser);

        $walletAcc = $this->tenantUser->walletAccount('IDR');
        $balanceWhileLeased = (int) $walletAcc->cached_balance;

        // 2. Terminasi dengan potongan tunggakan listrik/utilitas Rp5.000.000 (sisa Rp10.000.000 direfund)
        $terminateAction = app(TerminateLeaseAction::class);
        $terminatedLease = $terminateAction->handle(
            lease: $lease,
            outstandingDeductions: 5000000,
            reason: 'Tenant relokasi ke kota lain'
        );

        $this->assertEquals(LeaseStatus::TERMINATED, $terminatedLease->status);
        $this->assertEquals(DepositStatus::PARTIALLY_APPLIED, $terminatedLease->deposit_status);
        $this->assertNotNull($terminatedLease->terminated_at);

        // Unit kembali AVAILABLE
        $this->assertEquals(UnitStatus::AVAILABLE, $this->unit->fresh()->status);

        // 3. Saldo tenant bertambah sebesar sisa refund deposit (Rp10.000.000)
        $walletAcc->refresh();
        $this->assertEquals($balanceWhileLeased + 10000000, (int) $walletAcc->cached_balance);

        // 4. Akun pendapatan penyelesaian sewa menerima potongan Rp5.000.000
        $revAcc = LedgerAccount::where('code', 'revenue:mall:rent_settlement:IDR')->firstOrFail();
        $this->assertEquals(5000000, (int) $revAcc->cached_balance);
    }

    public function test_annual_escalation_increases_rent_accurately_for_year_2(): void
    {
        $baseRent = 20000000;
        $escalationPercent = 5.0; // 5% per tahun

        $lease = app(CreateLeaseAction::class)->handle(
            propertyId: $this->property->id,
            unitId: $this->unit->id,
            tenantId: $this->tenant->id,
            rentModel: RentModel::FIXED,
            startDate: '2025-01-01',
            endDate: '2026-12-31', // 2 tahun sewa
            baseMonthlyRent: $baseRent,
            serviceChargeMonthly: 3000000,
            annualEscalationPercent: $escalationPercent
        );

        // Tahun 1: sewa dasar = Rp20.000.000
        $this->assertEquals(20000000, $lease->calculateMonthlyRent(1));

        // Tahun 2: sewa dasar = Rp20.000.000 x 1.05 = Rp21.000.000
        $this->assertEquals(21000000, $lease->calculateMonthlyRent(2));

        // Tahun 3: sewa dasar = Rp20.000.000 x (1.05)^2 = Rp22.050.000
        $this->assertEquals(22050000, $lease->calculateMonthlyRent(3));

        // Perpanjang sewa (renew) untuk tahun ke-3
        $renewAction = app(RenewLeaseAction::class);
        $newLease = $renewAction->handle(
            oldLease: $lease,
            newEndDate: '2027-12-31'
        );

        $this->assertEquals('2027-01-01', $newLease->start_date->toDateString());
        $this->assertEquals('2027-12-31', $newLease->end_date->toDateString());
        // Tarif sewa baru di kontrak perpanjangan otomatis menggunakan tarif tereskalasi
        $this->assertGreaterThan($baseRent, $newLease->base_monthly_rent);
    }

    public function test_occupancy_query_calculates_gla_and_unit_percentages(): void
    {
        $query = app(OccupancyQuery::class);
        $result = $query->execute($this->property->id);

        $this->assertArrayHasKey('total_gla_sqm', $result);
        $this->assertArrayHasKey('leased_gla_sqm', $result);
        $this->assertArrayHasKey('occupancy_rate_percent', $result);
        $this->assertArrayHasKey('total_units', $result);
        $this->assertArrayHasKey('leased_units', $result);
        $this->assertArrayHasKey('floor_breakdown', $result);
        $this->assertArrayHasKey('expiring_soon_leases', $result);

        $this->assertGreaterThan(0, $result['total_gla_sqm']);
        $this->assertGreaterThan(0, $result['total_units']);
        $this->assertGreaterThanOrEqual(0, $result['occupancy_rate_percent']);
        $this->assertLessThanOrEqual(100, $result['occupancy_rate_percent']);

        // Setidaknya ada 3 lease aktif dari seeder (RM Sari Ranah, AutoServe, Starbucks)
        $this->assertGreaterThanOrEqual(3, $result['leased_units']);
    }

    public function test_mall_http_routes_and_public_directory(): void
    {
        // 1. Direktori tenant publik bisa diakses tanpa login (200 OK)
        $resPublic = $this->get(route('mall.directory.index'));
        $resPublic->assertStatus(200)
            ->assertSee('RM Sari Ranah')
            ->assertSee('Duta Mall');

        // 2. Site plan denah lantai memerlukan autentikasi
        $resGuest = $this->get(route('mall.site-plan.index'));
        $resGuest->assertRedirect('/login');

        // 3. User login dapat mengakses site plan
        $resAuth = $this->actingAs($this->admin)->get(route('mall.site-plan.index'));
        $resAuth->assertStatus(200)
            ->assertSee('Site Plan')
            ->assertSee('Okupansi Mall');

        // 4. Daftar kontrak sewa
        $resLeases = $this->actingAs($this->admin)->get(route('mall.leases.index'));
        $resLeases->assertStatus(200)
            ->assertSee('LSE-DM-2026-001');

        // 5. Daftar tenant mitra
        $resTenants = $this->actingAs($this->admin)->get(route('mall.tenants.index'));
        $resTenants->assertStatus(200)
            ->assertSee('PT Sari Ranah Minang');
    }

    public function test_bank_reconcile_remains_clean_after_all_mall_lease_operations(): void
    {
        $exitCode = Artisan::call('bank:reconcile');
        $this->assertEquals(0, $exitCode);
    }
}
