<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\Ledger;
use Modules\Mobility\Application\Actions\AssignVehicleToContractAction;
use Modules\Mobility\Application\Actions\BillExcessMileageAction;
use Modules\Mobility\Application\Actions\BillMonthlyFleetLeaseAction;
use Modules\Mobility\Application\Actions\CreditSlaPenaltyAction;
use Modules\Mobility\Application\Services\Psak73AmortizationEngine;
use Modules\Mobility\Domain\Models\FleetContract;
use Modules\Mobility\Domain\Models\FleetContractUnit;
use Modules\Mobility\Domain\Models\FleetInvoice;
use Modules\Mobility\Domain\Models\FleetSlaBreach;
use Tests\TestCase;

class MobilityFleetBillingAndPsak73Test extends TestCase
{
    use RefreshDatabase;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->ledger = app(Ledger::class);
    }

    private function createContract(int $monthlyRentalIdr = 20000000, int $durationMonths = 12): FleetContract
    {
        return FleetContract::create([
            'contract_number' => 'CTR-FLT-'.uniqid(),
            'party_id' => 9001,
            'monthly_rental_idr' => $monthlyRentalIdr,
            'total_lease_value_idr' => $monthlyRentalIdr * $durationMonths,
            'duration_months' => $durationMonths,
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => Carbon::parse('2026-12-31'),
            'max_downtime_hours_per_month' => 48,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_tagihan_bulanan_ber_key_deterministik_oto_fleet_bill_contract_periode(): void
    {
        $contract = $this->createContract(25000000, 12);
        $action = app(BillMonthlyFleetLeaseAction::class);

        $invoice = $action->execute(contract: $contract, period: '2026-10');

        $this->assertInstanceOf(FleetInvoice::class, $invoice);
        $this->assertSame(25000000, $invoice->rental_fee_idr);
        $this->assertSame('oto:fleet:bill:'.$contract->contract_number.':2026-10', $invoice->idempotency_key);
        $this->assertSame('ISSUED', $invoice->status);

        // Posting AR debit (-25 jt) & Revenue kredit (+25 jt)
        $arBalance = $this->ledger->getAccountBalance('ar:oto:fleet:9001:IDR');
        $this->assertSame(-25000000, $arBalance);

        $revBalance = $this->ledger->getAccountBalance('oto:fleet_lease_revenue:IDR');
        $this->assertSame(25000000, $revBalance);
    }

    public function test_tagihan_bulan_sama_dua_kali_menghasilkan_satu_invoice_dan_saldo_tetap(): void
    {
        $contract = $this->createContract(20000000, 12);
        $action = app(BillMonthlyFleetLeaseAction::class);

        $inv1 = $action->execute(contract: $contract, period: '2026-10');
        $inv2 = $action->execute(contract: $contract, period: '2026-10');

        $this->assertSame($inv1->id, $inv2->id);
        $this->assertSame(1, FleetInvoice::where('contract_id', $contract->id)->count());

        // Saldo piutang tetap 20 jt (tidak menjadi 40 jt)
        $arBalance = $this->ledger->getAccountBalance('ar:oto:fleet:9001:IDR');
        $this->assertSame(-20000000, $arBalance);
    }

    public function test_kredit_sla_dihitung_persis_fee_unit_dikali_bps(): void
    {
        $contract = $this->createContract(15000000, 12);
        $unit = FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => 101,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $action = app(CreditSlaPenaltyAction::class);

        // Pelanggaran SLA: downtime melebihi batas, penalti 500 bps (5%)
        // Fee 15.000.000 * 500 / 10.000 = 750.000 IDR
        $breach = FleetSlaBreach::create([
            'breach_uuid' => 'BREACH-UUID-777',
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'incident_date' => '2026-10-15',
            'downtime_hours' => 60, // melebihi batas 48 jam
            'breach_type' => 'DOWNTIME_EXCESS',
            'penalty_bps' => 500,
            'penalty_amount_idr' => 750000,
            'status' => 'RECORDED',
        ]);

        $creditedBreach = $action->execute($breach);

        $this->assertSame('CREDITED', $creditedBreach->status);
        $this->assertSame(750000, $creditedBreach->penalty_amount_idr);

        // Ledger: debit expense:oto:fleet_sla_credit:IDR (-750.000) dan kredit ar:oto:fleet:9001:IDR (+750.000)
        $expBalance = $this->ledger->getAccountBalance('expense:oto:fleet_sla_credit:IDR');
        $this->assertSame(-750000, $expBalance);

        $arBalance = $this->ledger->getAccountBalance('ar:oto:fleet:9001:IDR');
        $this->assertSame(750000, $arBalance);
    }

    public function test_kredit_sla_idempoten_per_breach_uuid(): void
    {
        $contract = $this->createContract(15000000, 12);
        $unit = FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => 102,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $action = app(CreditSlaPenaltyAction::class);

        $breach = FleetSlaBreach::create([
            'breach_uuid' => 'BREACH-UUID-888',
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'incident_date' => '2026-10-18',
            'downtime_hours' => 55,
            'breach_type' => 'DOWNTIME_EXCESS',
            'penalty_bps' => 500,
            'penalty_amount_idr' => 750000,
            'status' => 'RECORDED',
        ]);

        $action->execute($breach);
        $action->execute($breach);

        // Beban SLA hanya bertambah sekali 750.000
        $expBalance = $this->ledger->getAccountBalance('expense:oto:fleet_sla_credit:IDR');
        $this->assertSame(-750000, $expBalance);
    }

    public function test_amortisasi_psak73_total_pokok_dan_bunga_sama_dengan_total_pembayaran_persis(): void
    {
        $engine = app(Psak73AmortizationEngine::class);

        // Kontrak: Sewa bulanan 10.000.000 IDR selama 12 bulan (Total pembayaran = 120.000.000 IDR)
        // PV Liabilitas Awal = 110.000.000 IDR
        // Suku bunga implisit diskonto = 840 bps per tahun (0.007 per bulan)
        $schedule = $engine->generateSchedule(
            contractId: 501,
            presentValueLiabilityIdr: 110000000,
            monthlyPaymentIdr: 10000000,
            durationMonths: 12,
            annualRateBps: 840
        );

        $this->assertCount(12, $schedule);

        $sumPrincipal = 0;
        $sumInterest = 0;
        $sumPayment = 0;

        foreach ($schedule as $row) {
            $sumPrincipal += $row['principal_idr'];
            $sumInterest += $row['interest_idr'];
            $sumPayment += $row['payment_idr'];
        }

        $this->assertSame($sumPayment, $sumPrincipal + $sumInterest, 'Sigma Pokok + Sigma Bunga harus sama persis dengan Sigma Pembayaran');
        $this->assertSame(120000000, $sumPayment);
    }

    public function test_amortisasi_psak73_liabilitas_akhir_adalah_nol_persis(): void
    {
        $engine = app(Psak73AmortizationEngine::class);

        $schedule = $engine->generateSchedule(
            contractId: 502,
            presentValueLiabilityIdr: 100000000,
            monthlyPaymentIdr: 9000000,
            durationMonths: 12,
            annualRateBps: 750
        );

        $lastRow = end($schedule);
        $this->assertSame(0, $lastRow['ending_liability_idr'], 'Sisa liabilitas di akhir periode kontrak harus tepat 0');
    }

    public function test_tagihan_kelebihan_jarak_memakai_odometer_integer_meter(): void
    {
        $contract = $this->createContract(10000000, 12);
        $action = app(BillExcessMileageAction::class);

        // Batas kuota per bulan: 3.000 km = 3.000.000 meter
        // Pembacaan awal: 10.000.000 meter, pembacaan akhir: 13.500.000 meter
        // Jarak tempuh: 3.500.000 meter (Kelebihan: 500.000 meter = 500 km)
        // Tarif kelebihan: 2.000 IDR per km (2 IDR per meter)
        // Tagihan: 500 km * 2.000 IDR = 1.000.000 IDR
        $invoice = $action->execute(
            contract: $contract,
            period: '2026-10',
            startOdometerMeters: 10000000,
            endOdometerMeters: 13500000,
            allowedOdometerMeters: 3000000,
            ratePerKmIdr: 2000
        );

        $this->assertSame(1000000, $invoice->excess_mileage_fee_idr);
    }

    public function test_tagihan_kelebihan_jarak_nol_bila_di_bawah_kuota(): void
    {
        $contract = $this->createContract(10000000, 12);
        $action = app(BillExcessMileageAction::class);

        // Jarak 2.000.000 meter <= kuota 3.000.000 meter -> kelebihan 0
        $invoice = $action->execute(
            contract: $contract,
            period: '2026-10',
            startOdometerMeters: 10000000,
            endOdometerMeters: 12000000,
            allowedOdometerMeters: 3000000,
            ratePerKmIdr: 2000
        );

        $this->assertSame(0, $invoice->excess_mileage_fee_idr);
    }

    public function test_penugasan_unit_armada_ke_kontrak_state_transitions(): void
    {
        $contract = $this->createContract(10000000, 12);

        $unit = FleetContractUnit::create([
            'contract_id' => $contract->id,
            'vehicle_id' => 201,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $this->assertSame('ASSIGNED', $unit->status);

        $unit->update(['status' => 'REPLACED']);
        $this->assertSame('REPLACED', $unit->fresh()->status);

        $unit->update(['status' => 'RETURNED']);
        $this->assertSame('RETURNED', $unit->fresh()->status);
    }

    public function test_dua_unit_aktif_tidak_boleh_memiliki_vehicle_id_yang_sama(): void
    {
        $c1 = $this->createContract(10000000, 12);
        $c2 = $this->createContract(10000000, 12);

        FleetContractUnit::create([
            'contract_id' => $c1->id,
            'vehicle_id' => 301,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Vehicle is already assigned to an active contract');

        $action = app(AssignVehicleToContractAction::class);
        $action->execute(contractId: $c2->id, vehicleId: 301);
    }
}
