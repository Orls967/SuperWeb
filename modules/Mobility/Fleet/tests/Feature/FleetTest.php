<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Fleet\Application\Services\FleetLeasingService;
use Modules\Mobility\Fleet\Application\Services\FleetSlaMonitorService;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle1;

    protected Vehicle $vehicle2;

    protected Vehicle $vehicle3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->vehicle1 = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 1111 FLT',
            'brand' => 'Toyota',
            'model' => 'Hilux Single Cabin',
            'year' => 2023,
            'vin' => 'MR011111111111111',
        ]);

        $this->vehicle2 = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 2222 FLT',
            'brand' => 'Toyota',
            'model' => 'Hilux Double Cabin',
            'year' => 2023,
            'vin' => 'MR022222222222222',
        ]);

        $this->vehicle3 = Vehicle::create([
            'user_id' => $this->user->id,
            'plate_number' => 'B 3333 FLT',
            'brand' => 'Mitsubishi',
            'model' => 'Triton 4x4',
            'year' => 2024,
            'vin' => 'MMB33333333333333',
        ]);

        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_fleet_contract_creation_and_double_lease_prevention(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 15000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $this->assertSame(180000000, $contract->total_lease_value_idr);
        $this->assertCount(1, $contract->units);
        $this->assertGreaterThan(0, $contract->present_value_liability_idr);
        $this->assertSame($contract->present_value_liability_idr, $contract->remaining_liability_idr);

        // Anti-lease ganda: Mencoba sewa vehicle1 yang sedang aktif di kontrak lain harus gagal
        $this->expectException(\InvalidArgumentException::class);
        $service->createLeaseContract(
            partyId: 502,
            durationMonths: 6,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-02-01')
        );
    }

    public function test_fleet_lease_amortization_sums_to_total_lease_value_and_completes(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 6,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle2->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $this->assertSame(60000000, $contract->total_lease_value_idr);

        for ($i = 1; $i <= 6; $i++) {
            $service->amortizeMonthly($contract, $i, 2026);
        }

        $contract->refresh();
        $this->assertSame(60000000, $contract->accumulated_amortized_idr);
        $this->assertSame(0, $contract->remaining_liability_idr);
        $this->assertSame('completed', $contract->status);
    }

    public function test_fleet_lease_amortization_idempotency_same_month_one_posting_next_month_two(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 6,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle2->id],
            startDate: Carbon::parse('2026-01-01')
        );

        // 1. Jalankan pertama kali di bulan 1 -> 1 posting, saldo bertambah 10jt
        $service->amortizeMonthly($contract, 1, 2026);
        $contract->refresh();
        $this->assertSame(10000000, $contract->accumulated_amortized_idr);
        $this->assertDatabaseCount('bank_ledger_transactions', 1);

        // 2. Jalankan kedua kali di bulan 1 yang sama -> tetap 1 posting, saldo tetap 10jt
        $service->amortizeMonthly($contract, 1, 2026);
        $contract->refresh();
        $this->assertSame(10000000, $contract->accumulated_amortized_idr);
        $this->assertDatabaseCount('bank_ledger_transactions', 1);

        // 3. Jalankan di bulan berikutnya (bulan 2) -> total 2 posting, saldo 20jt
        $service->amortizeMonthly($contract, 2, 2026);
        $contract->refresh();
        $this->assertSame(20000000, $contract->accumulated_amortized_idr);
        $this->assertDatabaseCount('bank_ledger_transactions', 2);
    }

    public function test_psak_73_schedule_principal_and_interest_invariants(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 15000000,
            vehicleIds: [$this->vehicle3->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $schedule = $service->generatePsak73Schedule($contract);

        $this->assertCount(12, $schedule);

        $sumPrincipal = 0;
        $sumInterest = 0;
        $sumPayment = 0;

        foreach ($schedule as $row) {
            $sumPrincipal += $row['principal'];
            $sumInterest += $row['interest'];
            $sumPayment += $row['payment'];
            $this->assertSame($row['payment'], $row['principal'] + $row['interest']);
        }

        // Invarian PSAK 73: Total pokok sama persis dengan PV, total pembayaran sama persis dengan total lease value
        $this->assertSame($contract->present_value_liability_idr, $sumPrincipal);
        $this->assertSame($contract->total_lease_value_idr, $sumPayment);
        $this->assertSame($contract->total_lease_value_idr, $sumPrincipal + $sumInterest);
    }

    public function test_sla_breach_telematics_and_workshop_creates_ar_credit_note(): void
    {
        /** @var FleetLeasingService $leasingService */
        $leasingService = app(FleetLeasingService::class);
        /** @var FleetSlaMonitorService $slaService */
        $slaService = app(FleetSlaMonitorService::class);

        $contract = $leasingService->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01')
        );

        // Amortize 1 month first to establish AR debit
        $leasingService->amortizeMonthly($contract, 1, 2026);

        // Toleransi SLA adalah 24 jam / bulan. Telematika grounded 20 jam + bengkel 10 jam = 30 jam (Breach 6 jam)
        $slaLog = $slaService->recordMonthlyDowntime(
            contract: $contract,
            vehicleId: $this->vehicle1->id,
            incidentMonth: '2026-01',
            telematicsGroundedHours: 20,
            workshopServiceHours: 10
        );

        $this->assertSame(30, $slaLog->total_downtime_hours);
        $this->assertSame(6, $slaLog->breach_hours);
        $this->assertSame(300000, $slaLog->penalty_credit_idr); // 6 jam * Rp 50.000
        $this->assertTrue($slaLog->is_compensated);

        $this->assertDatabaseHas('oto_fleet_sla_logs', [
            'contract_id' => $contract->id,
            'incident_month' => '2026-01',
            'breach_hours' => 6,
            'penalty_credit_idr' => 300000,
            'is_compensated' => true,
        ]);

        $this->assertDatabaseHas('bank_ledger_transactions', [
            'reference_type' => 'fleet_contract',
            'reference_id' => (string) $contract->id,
            'type' => 'sla_compensation',
        ]);
    }

    public function test_excess_mileage_charge_posts_to_ledger(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01')
        );

        // Allowed: 0 baseline + 30.000 km = 30.000 km. Actual: 32.500 km (Excess 2.500 km * Rp 1.500 = Rp 3.750.000)
        $fee = $service->chargeExcessMileage($contract, $this->vehicle1->id, 32500);

        $this->assertSame(3750000, $fee);

        $this->assertDatabaseHas('oto_fleet_contract_units', [
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle1->id,
            'current_odometer_km' => 32500,
        ]);
    }

    public function test_four_eyes_approval_workflow(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 36,
            monthlyRentalIdr: 20000000, // Total > 500 jt -> requires four eyes
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01'),
            requiresFourEyes: true
        );

        $this->assertSame('pending_approval', $contract->status);
        $this->assertTrue($contract->requires_four_eyes);

        // Approver 1 approves
        $service->approveContract($contract, $approver1->id);
        $contract->refresh();
        $this->assertSame('pending_approval', $contract->status);
        $this->assertSame($approver1->id, $contract->approved_by_primary_id);

        // Same approver cannot approve twice
        try {
            $service->approveContract($contract, $approver1->id);
            $this->fail('Expected InvalidArgumentException for same secondary approver');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('different', $e->getMessage());
        }

        // Approver 2 approves
        $service->approveContract($contract, $approver2->id);
        $contract->refresh();
        $this->assertSame('active', $contract->status);
        $this->assertSame($approver2->id, $contract->approved_by_secondary_id);
    }

    public function test_end_of_lease_inspection_and_return_to_autodex(): void
    {
        /** @var FleetLeasingService $service */
        $service = app(FleetLeasingService::class);

        $contract = $service->createLeaseContract(
            partyId: 501,
            durationMonths: 12,
            monthlyRentalIdr: 10000000,
            vehicleIds: [$this->vehicle1->id],
            startDate: Carbon::parse('2026-01-01')
        );

        $service->completeEndOfLeaseReturn($contract, $this->vehicle1->id, 'Inspeksi mulus, baterai 98%, siap jual AutoDex');

        $this->assertDatabaseHas('oto_fleet_contract_units', [
            'contract_id' => $contract->id,
            'vehicle_id' => $this->vehicle1->id,
            'status' => 'returned',
            'condition_report_notes' => 'Inspeksi mulus, baterai 98%, siap jual AutoDex',
        ]);

        $contract->refresh();
        $this->assertSame('completed', $contract->status);
    }
}
