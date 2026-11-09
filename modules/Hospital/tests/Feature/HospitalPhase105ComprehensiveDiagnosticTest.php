<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hospital\Application\Services\ComprehensiveLoincDictionary;
use Modules\Hospital\Application\Services\CriticalValuePanicAlertCoordinator;
use Modules\Hospital\Application\Services\CtRadiationDoseTracker;
use Modules\Hospital\Application\Services\DiagnosticImagingPacsIntegrationService;
use Modules\Hospital\Application\Services\DicomWebMetadataExtractor;
use Modules\Hospital\Application\Services\LoincCatalogSearchService;
use Modules\Hospital\Application\Services\OutsourcedLabBridgingGateway;
use Modules\Hospital\Application\Services\PathologistVerificationService;
use Modules\Hospital\Application\Services\SpecimenCustodyHashChainTracker;
use Modules\Hospital\Application\Services\WestgardMultiruleEvaluator;
use Modules\Hospital\Domain\Enums\SpecimenCustodyStatus;
use Modules\Hospital\Domain\Enums\WestgardRule;
use Modules\Hospital\Domain\Models\CriticalRangeNotification;
use Modules\Hospital\Domain\Models\DicomInstance;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use Modules\Hospital\Domain\Models\LabOutsourcingManifest;
use Modules\Hospital\Domain\Models\LabPartnerContract;
use Modules\Hospital\Domain\Models\LabResult;
use Modules\Hospital\Domain\Models\LabSpecimen;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\PharmacyBranch;
use RuntimeException;
use Tests\TestCase;

class HospitalPhase105ComprehensiveDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected Patient $patient;

    protected User $doctor;

    protected User $pathologist;

    protected User $technologist;

    protected User $nurse;

    protected User $auditor;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);

        $this->doctor = User::factory()->create(['role' => 'doctor']);
        $this->pathologist = User::factory()->create(['role' => 'doctor']);
        $this->technologist = User::factory()->create(['role' => 'pharmacist']);
        $this->nurse = User::factory()->create(['role' => 'nurse']);
        $this->auditor = User::factory()->create(['role' => 'auditor']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->patient = Patient::create([
            'mrn' => 'MRN-PHASE105-01',
            'name' => 'Budi Santoso Diagnostik',
            'date_of_birth' => '1982-06-15',
            'blood_type' => 'O+',
            'passport_hash' => hash('sha256', 'PASSPORT:MRN-PHASE105-01'),
        ]);

        ComprehensiveLoincDictionary::seedStandardCatalog();
    }

    public function test_loinc_standard_catalog_seed_and_search_retrieval(): void
    {
        $searchService = app(LoincCatalogSearchService::class);

        // 1. Search by exact analyte name
        $results = $searchService->search('Potassium');
        $this->assertNotEmpty($results);
        $this->assertTrue($results->contains('loinc_num', '2823-3'));

        // 2. Search by class CHEM
        $chemResults = $searchService->search('', className: 'CHEM');
        $this->assertGreaterThanOrEqual(10, $chemResults->count());

        // 3. Match best LOINC for clinical order
        $matched = $searchService->matchBestLoinc('Troponin');
        $this->assertNotNull($matched);
        $this->assertSame('10839-9', $matched->loinc_num);
        $this->assertTrue($matched->isValuePanic(0.08));
        $this->assertFalse($matched->isValuePanic(0.02));
    }

    public function test_three_hop_specimen_custody_tracking_with_canonical_hash_chain(): void
    {
        $tracker = app(SpecimenCustodyHashChainTracker::class);

        $specimen = $tracker->initializeSpecimen($this->patient, 'BLOOD_EDTA');
        $this->assertSame(SpecimenCustodyStatus::COLLECTED->value, $specimen->status);
        $this->assertNotEmpty($specimen->custody_hash);

        // Hop 1: Phlebotomist ke Kurir
        $hop1 = $tracker->recordHop(
            specimen: $specimen,
            hopNumber: 1,
            fromHandler: 'Ns. Ratna (NUR-01)',
            toHandler: 'Agus Kurir (LOG-02)',
            temperatureCelsius: 4.2,
            notes: 'Coolbox vacuum seal verified'
        );
        $this->assertSame(SpecimenCustodyStatus::IN_TRANSIT->value, $specimen->fresh()->status);
        $this->assertSame($specimen->fresh()->custody_hash, $hop1->custody_hash);

        // Hop 2: Kurir ke Core Lab Receiving
        $hop2 = $tracker->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 2,
            fromHandler: 'Agus Kurir (LOG-02)',
            toHandler: 'Siti Lab Triase (ATLM-04)',
            temperatureCelsius: 4.5,
            notes: 'Received intact at central laboratory'
        );
        $this->assertSame(SpecimenCustodyStatus::RECEIVED_LAB->value, $specimen->fresh()->status);
        $this->assertSame($hop1->custody_hash, $hop2->prev_hash);

        // Hop 3: Lab Receiving ke Workstation Analis
        $hop3 = $tracker->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 3,
            fromHandler: 'Siti Lab Triase (ATLM-04)',
            toHandler: 'Dr. Hendra Sp.PK (PATH-01)',
            temperatureCelsius: 4.0,
            notes: 'Aliquot for clinical biochemistry'
        );
        $this->assertSame(SpecimenCustodyStatus::PROCESSED->value, $specimen->fresh()->status);

        // Verifikasi keutuhan rantai hash 100%
        $this->assertTrue($tracker->verifyChainIntegrity($specimen->fresh()));
    }

    public function test_specimen_cold_chain_breach_rejects_and_prevents_subsequent_hops(): void
    {
        $tracker = app(SpecimenCustodyHashChainTracker::class);
        $specimen = $tracker->initializeSpecimen($this->patient, 'BLOOD_EDTA');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Specimen rejected due to cold-chain temperature breach: 12.5C');

        $tracker->recordHop(
            specimen: $specimen,
            hopNumber: 1,
            fromHandler: 'Ns. Ratna',
            toHandler: 'Agus Kurir',
            temperatureCelsius: 12.5 // Melebihi batas aman 10.0 C
        );
    }

    public function test_tampered_custody_log_fails_cryptographic_verification(): void
    {
        $tracker = app(SpecimenCustodyHashChainTracker::class);
        $specimen = $tracker->initializeSpecimen($this->patient, 'SERUM');

        $hop1 = $tracker->recordHop(
            specimen: $specimen,
            hopNumber: 1,
            fromHandler: 'Clinic Nurse',
            toHandler: 'Courier Hub',
            temperatureCelsius: 4.0
        );

        $hop2 = $tracker->recordHop(
            specimen: $specimen->fresh(),
            hopNumber: 2,
            fromHandler: 'Courier Hub',
            toHandler: 'Core Lab',
            temperatureCelsius: 4.2
        );

        // Simulasi sabotase data di database
        $hop1->update([
            'custody_hash' => hash('sha256', 'TAMPERED_MALICIOUS_HASH'),
        ]);

        $this->assertFalse($tracker->verifyChainIntegrity($specimen->fresh()));
    }

    public function test_critical_panic_value_evaluation_and_pathologist_verification_release(): void
    {
        $coordinator = app(CriticalValuePanicAlertCoordinator::class);
        $pathologistService = app(PathologistVerificationService::class);
        $tracker = app(SpecimenCustodyHashChainTracker::class);

        $specimen = $tracker->initializeSpecimen($this->patient, 'SERUM');

        // Evaluasi Kalium Kritis (2.1 mEq/L, panic low 2.5)
        $eval = $coordinator->evaluateCriticalPanic('POTASSIUM', 2.10);
        $this->assertTrue($eval['is_panic']);
        $this->assertSame('CRITICAL_HOLD_AND_PAGE', $eval['protocol']);

        // Menerbitkan notifikasi darurat
        $notif = $coordinator->dispatchCriticalNotification(
            specimen: $specimen,
            testCode: 'POTASSIUM',
            analyteName: 'Serum Potassium (K+)',
            measuredValue: 2.10,
            panicThreshold: 2.50,
            panicType: 'LOW',
            protocol: $eval['protocol'],
            doctorPhone: '081234567899'
        );

        $this->assertInstanceOf(CriticalRangeNotification::class, $notif);
        $this->assertSame('CRITICAL_HOLD', $notif->status);

        // Hasil lab dalam status CRITICAL_HOLD
        $labResult = LabResult::create([
            'result_code' => 'RES-CRIT-001',
            'specimen_id' => $specimen->id,
            'test_code' => 'POTASSIUM',
            'numeric_value' => 2.10,
            'is_critical' => true,
            'status' => 'CRITICAL_HOLD',
        ]);

        // Verifikasi dan pengesahan patolog
        $verifiedResult = $pathologistService->verifyAndRelease(
            result: $labResult,
            pathologist: $this->pathologist,
            interpretation: 'Severe hypokalemia confirmed, immediate IV potassium drip recommended.'
        );

        $this->assertSame('PATHOLOGIST_VERIFIED', $verifiedResult->status);
        $this->assertNotEmpty($verifiedResult->verified_by_pathologist_hash);

        // Verifikasi pengakuan pendapatan lab di buku besar
        $labRev = LedgerAccount::where('code', 'hsp:lab_revenue:IDR')->first();
        $this->assertNotNull($labRev);
        $this->assertGreaterThan(0, (int) $labRev->cached_balance);
    }

    public function test_westgard_multirule_quality_control_evaluation(): void
    {
        $evaluator = app(WestgardMultiruleEvaluator::class);

        // 1. In-control run (Z-score 0.5)
        $run1 = $evaluator->evaluateQcRun(
            analyzerCode: 'ROCHE-COBAS-8000',
            lotNumber: 'LOT-QC-202611',
            controlLevel: 'LEVEL_1',
            testCode: 'POTASSIUM',
            targetMean: 4.0,
            targetSd: 0.2,
            measuredValue: 4.10 // Z = 0.5
        );
        $this->assertFalse($run1['is_rejected']);
        $this->assertNull($run1['violation']);

        // 2. Warning rule 1-2s (Z-score 2.2)
        $run2 = $evaluator->evaluateQcRun(
            analyzerCode: 'ROCHE-COBAS-8000',
            lotNumber: 'LOT-QC-202611',
            controlLevel: 'LEVEL_1',
            testCode: 'POTASSIUM',
            targetMean: 4.0,
            targetSd: 0.2,
            measuredValue: 4.44, // Z = 2.2
            recentZScores: [0.5]
        );
        $this->assertFalse($run2['is_rejected']); // Warning only
        $this->assertSame(WestgardRule::RULE_1_2S->value, $run2['violation']);

        // 3. Rejection rule 1-3s (Z-score 3.5)
        $run3 = $evaluator->evaluateQcRun(
            analyzerCode: 'ROCHE-COBAS-8000',
            lotNumber: 'LOT-QC-202611',
            controlLevel: 'LEVEL_1',
            testCode: 'POTASSIUM',
            targetMean: 4.0,
            targetSd: 0.2,
            measuredValue: 4.70, // Z = 3.5
            recentZScores: [2.2, 0.5]
        );
        $this->assertTrue($run3['is_rejected']);
        $this->assertSame(WestgardRule::RULE_1_3S->value, $run3['violation']);

        // 4. Rejection rule 2-2s (2 consecutive > 2 SD on same side)
        $run4 = $evaluator->evaluateQcRun(
            analyzerCode: 'ROCHE-COBAS-8000',
            lotNumber: 'LOT-QC-202611',
            controlLevel: 'LEVEL_2',
            testCode: 'POTASSIUM',
            targetMean: 6.0,
            targetSd: 0.3,
            measuredValue: 6.66, // Z = 2.2
            recentZScores: [2.3] // previous also > 2 SD
        );
        $this->assertTrue($run4['is_rejected']);
        $this->assertSame(WestgardRule::RULE_2_2S->value, $run4['violation']);
    }

    public function test_dicom_pacs_metadata_extraction_and_wado_rs_payload(): void
    {
        $pacsService = app(DiagnosticImagingPacsIntegrationService::class);
        $extractor = app(DicomWebMetadataExtractor::class);

        $study = $pacsService->recordImagingStudy(
            patient: $this->patient,
            modality: 'CT',
            bodyPart: 'HEAD_BRAIN_CONTRAST',
            turnaroundMinutes: 22,
            feeIdr: 1_850_000,
            findings: 'Normal ventricular system. No acute intracranial hemorrhage.',
            priority: 'STAT'
        );

        $series = $pacsService->createSeries(
            study: $study,
            seriesUid: '1.2.840.113619.2.55.3.4219.1001',
            modality: 'CT',
            sliceCount: 160,
            storageUri: 's3://pacs-vault/studies/202611/head-ct.dcm'
        );

        // Tambah SOP Instance irisan citra
        DicomInstance::create([
            'series_id' => $series->id,
            'sop_instance_uid' => '1.2.840.113619.2.55.3.4219.1001.1',
            'instance_number' => 1,
            'sop_class_uid' => '1.2.840.10008.5.1.4.1.1.2',
            'rows' => 512,
            'columns' => 512,
            'window_center' => 40.0,
            'window_width' => 80.0,
            'pixel_spacing' => '0.5\\0.5',
            'storage_path' => 'studies/head/slice1.dcm',
            'checksum_sha256' => hash('sha256', 'DICOM_PIXEL_DATA_SLICE_1'),
        ]);

        $metadata = $extractor->extractStudyViewerMetadata($study);

        $this->assertSame($study->study_instance_uid, $metadata['study_instance_uid']);
        $this->assertSame('CT', $metadata['modality']);
        $this->assertNotEmpty($metadata['window_presets']);
        $this->assertNotEmpty($metadata['series']);
        $this->assertCount(1, $metadata['series'][0]['instances']);
    }

    public function test_ct_radiation_dosimetry_tracking_and_drl_exceeded_detection(): void
    {
        $doseTracker = app(CtRadiationDoseTracker::class);
        $pacsService = app(DiagnosticImagingPacsIntegrationService::class);

        $study = $pacsService->recordImagingStudy(
            patient: $this->patient,
            modality: 'CT',
            bodyPart: 'HEAD_BRAIN',
            turnaroundMinutes: 20,
            feeIdr: 1_500_000
        );

        // 1. Dosis normal patuh DRL (CTDI 50 mGy, panjang pemindaian 15 cm -> DLP 750 mGy.cm <= 1000)
        $normalDose = $doseTracker->recordDoseMetrics(
            study: $study,
            ctdiVolMgy: 50.0,
            scanLengthCm: 15.0,
            patientEffectiveDiameterCm: 18.0
        );
        $this->assertFalse($normalDose['is_drl_exceeded']);
        $this->assertGreaterThan(0.0, $normalDose['effective_dose_msv']);

        // 2. Dosis berlebih melebihi DRL (CTDI 75 mGy > 60 DRL batas nasional)
        $excessDose = $doseTracker->recordDoseMetrics(
            study: $study,
            ctdiVolMgy: 75.0,
            scanLengthCm: 18.0
        );
        $this->assertTrue($excessDose['is_drl_exceeded']);
        $this->assertTrue($excessDose['log']->drl_exceeded);
        $this->assertStringContainsString('PERINGATAN', $excessDose['log']->safety_officer_review);
    }

    public function test_outsourced_lab_manifest_dispatch_and_hl7_observation_bridging(): void
    {
        $gateway = app(OutsourcedLabBridgingGateway::class);
        $tracker = app(SpecimenCustodyHashChainTracker::class);

        $contract = LabPartnerContract::create([
            'partner_name' => 'Prodia Reference Laboratory',
            'contract_code' => 'CONT-PRODIA-2026',
            'discount_percentage' => 15.0,
            'max_turnaround_hours' => 48,
            'is_active' => true,
        ]);

        $specimen = $tracker->initializeSpecimen($this->patient, 'SWAB_MOLECULAR');

        $manifest = $gateway->dispatchManifest(
            contract: $contract,
            specimens: [$specimen],
            courierBatchBarcode: 'COOLBOX-EXT-8891',
            departureTempCelsius: 3.8
        );

        $this->assertInstanceOf(LabOutsourcingManifest::class, $manifest);
        $this->assertSame(1, $manifest->specimen_count);
        $this->assertSame('DISPATCHED', $manifest->status);
        $this->assertSame('IN_TRANSIT', $specimen->fresh()->status);

        // Simulasi penerimaan hasil HL7 v2.5 ORU^R01 dari Prodia
        $hl7Payload = [
            'message_control_id' => 'MSG-HL7-99120',
            'obr_order_num' => 'ORD-REF-001',
            'obx_segments' => [
                [
                    'loinc_code' => '94500-6',
                    'value' => 'SARS-CoV-2 RNA Not Detected',
                    'units' => 'index',
                    'abnormal_flag' => 'N',
                ],
            ],
        ];

        $results = $gateway->ingestHl7ObservationResults($specimen, $hl7Payload);
        $this->assertCount(1, $results);
        $this->assertSame('94500-6', $results[0]->test_code);
        $this->assertSame('AUTO_VERIFIED', $results[0]->status);
    }

    public function test_http_api_endpoints_for_phase_105(): void
    {
        // 1. GET /hospital/laboratory/catalog
        $this->actingAs($this->doctor)->get(route('hospital.laboratory.catalog.index'))
            ->assertStatus(200);

        $catalogJson = $this->actingAs($this->doctor)->getJson('/hospital/laboratory/catalog');
        $catalogJson->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 2. POST /hospital/laboratory/specimens/custody
        $specimen = LabSpecimen::create([
            'specimen_barcode' => 'SPEC-HTTP-105',
            'patient_id' => $this->patient->id,
            'sample_type' => 'BLOOD',
            'collected_at' => now(),
            'custody_hash' => 'GENESIS',
            'status' => 'COLLECTED',
        ]);

        $custodyResp = $this->actingAs($this->nurse)->postJson('/hospital/laboratory/specimens/custody', [
            'specimen_id' => $specimen->id,
            'hop_number' => 1,
            'from_handler' => 'Nurse Emergency',
            'to_handler' => 'Logistics Hub',
            'transport_temp_celsius' => 4.1,
            'notes' => 'Transfer on cold-chain',
        ]);
        $custodyResp->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. POST /hospital/laboratory/results/verify
        $verifyResp = $this->actingAs($this->doctor)->postJson('/hospital/laboratory/results/verify', [
            'test_code' => 'POTASSIUM',
            'numeric_value' => 2.10,
        ]);
        $verifyResp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_panic', true);

        // 4. POST /hospital/radiology/dicom/studies
        $studyResp = $this->actingAs($this->doctor)->postJson('/hospital/radiology/dicom/studies', [
            'patient_id' => $this->patient->id,
            'modality' => 'CT',
            'body_part' => 'HEAD_BRAIN',
            'turnaround_minutes' => 25,
            'fee_idr' => 1_500_000,
            'radiologist_findings' => 'Normal scan',
            'priority' => 'STAT',
        ]);
        $studyResp->assertStatus(200)
            ->assertJsonPath('status', 'success');
        $studyId = $studyResp->json('data.id');

        // 5. GET /hospital/radiology/dicom/studies/{id}/metadata
        $metaResp = $this->actingAs($this->doctor)->getJson("/hospital/radiology/dicom/studies/{$studyId}/metadata");
        $metaResp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.modality', 'CT');

        // 6. POST /hospital/laboratory/qc/evaluate
        $qcResp = $this->actingAs($this->technologist)->postJson('/hospital/laboratory/qc/evaluate', [
            'analyzer_code' => 'COBAS-C501',
            'lot_number' => 'LOT-202611-C',
            'control_level' => 'LEVEL_1',
            'test_code' => 'GLUCOSE',
            'target_mean' => 100.0,
            'target_sd' => 5.0,
            'measured_value' => 102.0,
        ]);
        $qcResp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.isRejected', false);

        // 7. POST /hospital/laboratory/clearings/settle
        $branchA = PharmacyBranch::create([
            'branch_code' => 'BR-HTTP-01',
            'name' => 'Apotek Pusat',
            'type' => 'INTERNAL',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);
        $branchB = PharmacyBranch::create([
            'branch_code' => 'BR-HTTP-02',
            'name' => 'Apotek Cabang',
            'type' => 'INTERNAL',
            'city' => 'Bandung',
            'is_active' => true,
        ]);
        $clearing = InterBranchClearing::create([
            'clearing_code' => 'CLR-HTTP-001',
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'reference_type' => 'REAGENT_LOT_TRANSFER',
            'reference_id' => 'LOT-REAG-99',
            'amount_idr' => 300_000,
            'settlement_status' => 'PENDING',
        ]);

        $settleResp = $this->actingAs($this->admin)->postJson('/hospital/laboratory/clearings/settle', [
            'clearing_id' => $clearing->id,
        ]);
        $settleResp->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.settlement_status', 'SETTLED');
    }
}
