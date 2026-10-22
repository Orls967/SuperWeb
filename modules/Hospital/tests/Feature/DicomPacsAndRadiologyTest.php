<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\DicomPacsImagingService;
use Modules\Hospital\Application\Services\HospitalLabAndImagingService;
use Modules\Hospital\Domain\Models\ImagingSeries;
use Modules\Hospital\Domain\Models\Patient;
use Tests\TestCase;

class DicomPacsAndRadiologyTest extends TestCase
{
    use RefreshDatabase;

    private DicomPacsImagingService $pacsService;

    private HospitalLabAndImagingService $imagingService;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pacsService = app(DicomPacsImagingService::class);
        $this->imagingService = app(HospitalLabAndImagingService::class);

        $this->seed(LedgerAccountsSeeder::class);

        $this->patient = Patient::create([
            'mrn' => 'MRN-RAD-01',
            'name' => 'Alice Radiology Patient',
            'date_of_birth' => '1979-02-14',
            'blood_type' => 'AB+',
            'passport_hash' => hash('sha256', 'PASSPORT-MRN-RAD-01'),
        ]);
    }

    public function test_dicom_series_attachment_to_imaging_study(): void
    {
        $study = $this->imagingService->recordImagingStudy(
            patient: $this->patient,
            modality: 'CT',
            bodyPart: 'HEAD_BRAIN_CONTRAST',
            turnaroundMinutes: 25,
            feeIdr: 1_850_000,
            findings: 'No acute intracranial hemorrhage. Ventricles and sulci normal for age.'
        );

        $series = $this->pacsService->createSeries(
            study: $study,
            seriesUid: '1.2.840.113619.2.55.3.4219.1',
            modality: 'CT',
            sliceCount: 160,
            storageUri: 's3://pacs-vault-internal/studies/2026/10/head-ct-series-1.dcm'
        );

        $this->assertInstanceOf(ImagingSeries::class, $series);
        $this->assertSame(160, $series->slice_count);
        $this->assertSame('CT', $series->modality);
    }

    public function test_stat_and_routine_turnaround_sla_evaluations(): void
    {
        // 1. STAT exam completed in 20 minutes -> SLA MET
        $statStudy = $this->imagingService->recordImagingStudy(
            patient: $this->patient,
            modality: 'XR',
            bodyPart: 'CHEST_AP',
            turnaroundMinutes: 20,
            feeIdr: 250_000,
            findings: 'Lungs clear bilaterally.'
        );

        $statEval = $this->pacsService->evaluateTurnaroundSla($statStudy, 'STAT');
        $this->assertTrue($statEval['is_within_sla']);
        $this->assertSame('SLA_MET', $statEval['status']);

        // 2. Delayed STAT exam (45 minutes) -> SLA BREACHED
        $delayedStudy = $this->imagingService->recordImagingStudy(
            patient: $this->patient,
            modality: 'MRI',
            bodyPart: 'LUMBAR_SPINE',
            turnaroundMinutes: 45,
            feeIdr: 3_200_000,
            findings: 'L4-L5 disc protrusion.'
        );

        $delayedEval = $this->pacsService->evaluateTurnaroundSla($delayedStudy, 'STAT');
        $this->assertFalse($delayedEval['is_within_sla']);
        $this->assertSame('SLA_BREACHED', $delayedEval['status']);
    }
}
