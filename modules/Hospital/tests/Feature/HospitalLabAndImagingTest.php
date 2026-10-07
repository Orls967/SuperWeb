<?php

namespace Modules\Hospital\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\HospitalLabAndImagingService;
use Modules\Hospital\Domain\Models\LabCatalog;
use Modules\Hospital\Domain\Models\Patient;
use RuntimeException;
use Tests\TestCase;

class HospitalLabAndImagingTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalLabAndImagingService $service;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalLabAndImagingService::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-LAB-01',
            'name' => 'Jane Lab Patient',
            'date_of_birth' => '1992-04-10',
            'blood_type' => 'A+',
            'passport_hash' => hash('sha256', 'PASSPORT-LAB-01'),
        ]);

        LabCatalog::create([
            'test_code' => 'POTASSIUM',
            'test_name' => 'Serum Potassium',
            'category' => 'BIOCHEMISTRY',
            'reference_min' => 3.5,
            'reference_max' => 5.1,
            'unit' => 'mEq/L',
            'price_idr' => 120_000,
        ]);

        // Register hospital ledger accounts
        $accounts = [
            'hsp:lab_receivable:IDR' => 'asset',
            'hsp:lab_revenue:IDR' => 'revenue',
            'hsp:imaging_receivable:IDR' => 'asset',
            'hsp:imaging_revenue:IDR' => 'revenue',
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

    public function test_105_2_and_105_6_a_cold_chain_breach_rejects_specimen_and_blocks_results(): void
    {
        $specimen = $this->service->collectSpecimen($this->patient, 'BLOOD');
        $this->assertEquals('COLLECTED', $specimen->status);
        $this->assertNotEmpty($specimen->custody_hash);

        // Cold chain breach (> 10 C)
        try {
            $this->service->transferCustody($specimen, 'COURIER-LOG-01', 14.5);
            $this->fail('Expected cold chain breach exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Specimen rejected due to cold-chain temperature breach', $e->getMessage());
        }

        $this->assertEquals('REJECTED', $specimen->refresh()->status);

        // Attempting to certify result on rejected specimen must fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Specimen chain-of-custody broken or rejected');

        $this->service->recordLabResult($specimen, 'POTASSIUM', 4.0);
    }

    public function test_105_3_and_105_6_b_critical_value_requires_pathologist_verification(): void
    {
        $specimen = $this->service->collectSpecimen($this->patient, 'BLOOD');
        $this->service->transferCustody($specimen, 'LAB-TECH-01', 4.5);

        // Critical high potassium: 6.8 mEq/L (Ref: 3.5 - 5.1) without pathologist signature -> CRITICAL_HOLD
        $resHold = $this->service->recordLabResult($specimen, 'POTASSIUM', 6.8);
        $this->assertTrue($resHold->is_critical);
        $this->assertEquals('CRITICAL_HOLD', $resHold->status);

        // Revenue should NOT be recognized yet on critical hold
        $rev = LedgerAccount::where('code', 'hsp:lab_revenue:IDR')->first();
        $this->assertEquals('0', (string) $rev->cached_balance);

        // With pathologist signature -> PATHOLOGIST_VERIFIED and ledger credited
        $resVerified = $this->service->recordLabResult(
            $specimen,
            'POTASSIUM',
            6.8,
            pathologistSignatureHash: hash('sha256', 'PATHOLOGIST-MD-001')
        );
        $this->assertEquals('PATHOLOGIST_VERIFIED', $resVerified->status);

        $rev->refresh();
        $this->assertEquals('-120000', (string) $rev->cached_balance);
    }

    public function test_105_5_imaging_study_completion_and_billing(): void
    {
        $study = $this->service->recordImagingStudy(
            patient: $this->patient,
            modality: 'CT',
            bodyPart: 'HEAD',
            turnaroundMinutes: 45,
            feeIdr: 1_850_000,
            findings: 'No acute intracranial hemorrhage or mass effect.'
        );

        $this->assertEquals('COMPLETED', $study->status);
        $this->assertEquals(1_850_000, $study->fee_idr);

        $ar = LedgerAccount::where('code', 'hsp:imaging_receivable:IDR')->first();
        $rev = LedgerAccount::where('code', 'hsp:imaging_revenue:IDR')->first();

        $this->assertEquals('1850000', (string) $ar->cached_balance);
        $this->assertEquals('-1850000', (string) $rev->cached_balance);
    }
}
