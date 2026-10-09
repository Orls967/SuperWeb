<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\HospitalRevenueCycleService;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\Patient;
use Tests\TestCase;

class HospitalRevenueCycleAndPharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalRevenueCycleService $service;

    protected Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalRevenueCycleService::class);

        $patient = Patient::create([
            'mrn' => 'MRN-REV-01',
            'name' => 'Surgery Patient',
            'date_of_birth' => '1985-06-20',
            'blood_type' => 'B+',
            'passport_hash' => 'HASH-PASSPORT-01',
        ]);

        $this->encounter = Encounter::create([
            'encounter_code' => 'ENC-SURG-01',
            'patient_id' => $patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'ADMITTED',
        ]);

        // Register hospital ledger accounts
        $accounts = [
            'hsp:hospital_revenue:IDR' => 'revenue',
            'hsp:patient_deposit_escrow:IDR' => 'liability',
            'hsp:unearned_deposit:IDR' => 'liability',
            'hsp:bpjs_receivable:IDR' => 'asset',
            'hsp:insurance_receivable:IDR' => 'asset',
            'hsp:patient_self_pay:IDR' => 'asset',
            'hsp:pharma_supplier_payable:IDR' => 'liability',
            'hsp:vaccine_dispute_escrow:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hospital {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_88_1_and_88_2_surgical_escrow_deposit_mixed_split_settlement_and_refund(): void
    {
        // 1. Inpatient admission with 20,000,000 IDR surgical escrow deposit hold
        $episode = $this->service->openEpisodeWithDeposit($this->encounter, 20_000_000);
        $this->assertEquals(20_000_000, $episode->escrow_deposit_idr);
        $this->assertEquals('OPEN', $episode->status);

        // 2. Add folio charges: Room (5jt) + Surgery (25jt) + Pharmacy (5jt) = 35,000,000 IDR total
        $this->service->addFolioItem($episode, 'BED_DAY', 'VIP Bed 2 nights', 2, 2_500_000);
        $this->service->addFolioItem($episode, 'PROCEDURE', 'Laparoscopic Appendectomy', 1, 25_000_000);
        $this->service->addFolioItem($episode, 'PHARMACY', 'Post-Op Antibiotics & Analgesics', 1, 5_000_000);

        $this->assertEquals(35_000_000, $episode->refresh()->total_charges_idr);

        // 3. Discharge mixed settlement:
        // BPJS covers 15,000,000 IDR
        // Private Insurance covers 12,000,000 IDR
        // Patient share = 35m - 27m = 8,000,000 IDR
        // Escrow deposit was 20,000,000 -> captures 8m, refunds 12m remainder!
        $settled = $this->service->settleEpisodeDischarge(
            episode: $episode,
            bpjsCoverageIdr: 15_000_000,
            insuranceCopayIdr: 12_000_000
        );

        $this->assertEquals('SETTLED', $settled->status);
        $this->assertEquals(8_000_000, $settled->patient_share_idr);

        // Verify ledger balance invariants:
        // Revenue credited -35,000,000 IDR
        // BPJS AR +15,000,000 IDR
        // Insurance AR +12,000,000 IDR
        // Patient escrow captured +8,000,000 IDR
        $rev = LedgerAccount::where('code', 'hsp:hospital_revenue:IDR')->first();
        $bpjs = LedgerAccount::where('code', 'hsp:bpjs_receivable:IDR')->first();
        $ins = LedgerAccount::where('code', 'hsp:insurance_receivable:IDR')->first();

        $this->assertEquals('-35000000', (string) $rev->cached_balance);
        $this->assertEquals('15000000', (string) $bpjs->cached_balance);
        $this->assertEquals('12000000', (string) $ins->cached_balance);
    }

    public function test_88_3_e_prescription_contraindication_alert(): void
    {
        // Patient is currently taking Warfarin -> prescribing Aspirin flags contraindication
        $rx = $this->service->issuePrescription(
            encounter: $this->encounter,
            drugCode: 'ASPIRIN',
            drugName: 'Aspirin 80mg Cardioprotective',
            qty: 30,
            dosage: '1x daily after meals',
            currentPatientDrugs: ['WARFARIN', 'AMLODIPINE']
        );

        $this->assertTrue($rx->contraindication_alert);
        $this->assertEquals('PRESCRIBED', $rx->status);
    }

    public function test_88_5_vaccine_fridge_breach_quarantines_lot_and_holds_supplier_payable(): void
    {
        // Cold chain breach (> 8.0 C for refrigerated vaccines)
        $breach = $this->service->recordMedicalFridgeBreach(
            fridgeCode: 'FRIDGE-VACC-01',
            lotNumber: 'LOT-MRNA-9921',
            recordedTempC: 11.5,
            supplierId: 902,
            supplierPayableIdr: 50_000_000
        );

        $this->assertEquals('QUARANTINED', $breach->status);
        $this->assertEquals(50_000_000, $breach->held_supplier_payable_idr);

        // Verify ledger hold: Pharma payable +50m, Dispute escrow -50m
        $escrow = LedgerAccount::where('code', 'hsp:vaccine_dispute_escrow:IDR')->first();
        $this->assertEquals('-50000000', (string) $escrow->cached_balance);
    }
}
