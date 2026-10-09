<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\HospitalPublicHealthAndBpjsService;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\Patient;
use Tests\TestCase;

class HospitalPublicHealthAndBpjsTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalPublicHealthAndBpjsService $service;

    protected BillingEpisode $ep1;

    protected BillingEpisode $ep2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalPublicHealthAndBpjsService::class);

        $patient = Patient::create([
            'mrn' => 'MRN-BPJS-01',
            'name' => 'BPJS Enrollee',
            'date_of_birth' => '1988-11-20',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT-BPJS-01'),
        ]);

        $enc1 = Encounter::create([
            'encounter_code' => 'ENC-BPJS-01',
            'patient_id' => $patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'DISCHARGED',
        ]);

        $enc2 = Encounter::create([
            'encounter_code' => 'ENC-BPJS-02',
            'patient_id' => $patient->id,
            'encounter_type' => 'INPATIENT',
            'admitted_at' => now(),
            'status' => 'DISCHARGED',
        ]);

        $this->ep1 = BillingEpisode::create([
            'episode_code' => 'EP-BPJS-01',
            'encounter_id' => $enc1->id,
            'total_charges_idr' => 15_000_000,
            'status' => 'BILLED',
        ]);

        $this->ep2 = BillingEpisode::create([
            'episode_code' => 'EP-BPJS-02',
            'encounter_id' => $enc2->id,
            'total_charges_idr' => 20_000_000,
            'status' => 'BILLED',
        ]);

        // Register hospital ledger accounts
        $accounts = [
            'hsp:bpjs_receivable:IDR' => 'asset',
            'hsp:hospital_revenue:IDR' => 'revenue',
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

    public function test_107_1_and_107_6_a_gapless_claim_batch_and_denial_no_accrual(): void
    {
        // 1. Create Gapless Batch for 2026-10
        $batch = $this->service->createBpjsClaimBatch('2026-10', [
            ['episode' => $this->ep1, 'drg_code' => 'DRG-APPENDECTOMY', 'drg_tariff_idr' => 12_000_000],
            ['episode' => $this->ep2, 'drg_code' => 'DRG-CHOLECYSTECTOMY', 'drg_tariff_idr' => 18_000_000],
        ]);

        $this->assertEquals('BPJS-202610-0001', $batch->batch_number);
        $this->assertEquals(30_000_000, $batch->total_claimed_idr);
        $this->assertCount(2, $batch->items);

        // 2. Adjudicate batch: item 1 approved (12m), item 2 denied (18m)
        $item1 = $batch->items[0];
        $item2 = $batch->items[1];

        $adjudicated = $this->service->adjudicateBpjsClaimBatch($batch, [
            $item1->id => ['status' => 'APPROVED'],
            $item2->id => ['status' => 'DENIED', 'reason' => 'Missing operative pathology protocol'],
        ]);

        $this->assertEquals('PARTIALLY_DENIED', $adjudicated->status);
        $this->assertEquals(12_000_000, $adjudicated->approved_amount_idr);
        $this->assertEquals(18_000_000, $adjudicated->denied_amount_idr);

        // Verify ledger: ONLY approved 12,000,000 IDR is recognized, denied amount has 0 accrual
        $ar = LedgerAccount::where('code', 'hsp:bpjs_receivable:IDR')->first();
        $rev = LedgerAccount::where('code', 'hsp:hospital_revenue:IDR')->first();

        $this->assertEquals('12000000', (string) $ar->cached_balance);
        $this->assertEquals('-12000000', (string) $rev->cached_balance);
    }

    public function test_107_3_and_107_6_b_epidemic_surveillance_cluster_threshold_alert(): void
    {
        // Below threshold
        $survNormal = $this->service->reportSyndromicCluster('REGION-SBY-01', 'DENGUE', 15, 25);
        $this->assertFalse($survNormal->outbreak_alarm_triggered);
        $this->assertEquals('MONITORING', $survNormal->status);

        // Above threshold
        $survAlert = $this->service->reportSyndromicCluster('REGION-SBY-02', 'DENGUE', 32, 25);
        $this->assertTrue($survAlert->outbreak_alarm_triggered);
        $this->assertEquals('OUTBREAK_ALERT', $survAlert->status);
    }

    public function test_107_4_command_center_forecast_generation(): void
    {
        $forecast = $this->service->generateDemandForecast('2026-10-15', 50);

        // 50 * 1.15 = 57.5 -> round = 57 / 58
        $this->assertEquals((int) round(50 * 1.15), $forecast->predicted_admissions);
        $this->assertEquals((int) ceil($forecast->predicted_admissions / 4), $forecast->recommended_nurse_shifts);
        $this->assertEquals((int) ceil($forecast->predicted_admissions * 1.25), $forecast->recommended_active_beds);
        $this->assertEquals((int) ceil($forecast->predicted_admissions * 0.4), $forecast->blood_units_needed);
    }
}
