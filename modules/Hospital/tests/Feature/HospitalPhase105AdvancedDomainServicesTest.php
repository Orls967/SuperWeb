<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hospital\Application\Services\AutomatedPathologySlideStainingEngine;
use Modules\Hospital\Application\Services\BloodGasElectrolyteAcidBaseInterpreter;
use Modules\Hospital\Application\Services\ClinicalChemistryDeltaCheckEngine;
use Modules\Hospital\Application\Services\DicomPart10HeaderParser;
use Modules\Hospital\Application\Services\HistopathologyTissueBlockTraceabilityService;
use Modules\Hospital\Application\Services\LabAnalyzerCalibrationMaintenanceTracker;
use Modules\Hospital\Application\Services\LaboratoryExternalProficiencyTestingEvaluator;
use Modules\Hospital\Application\Services\MicrobiologyCultureAndSusceptibilityTracker;
use Modules\Hospital\Application\Services\RadiologyContrastMediaSafetyScreeningService;
use Modules\Hospital\Application\Services\RadiologyDoseOptimizationAecAnalyzer;
use Modules\Hospital\Application\Services\RadiologyModalityWorklistScheduler;
use Tests\TestCase;

class HospitalPhase105AdvancedDomainServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_chemistry_delta_check_engine_evaluates_rcv_and_flags(): void
    {
        $engine = new ClinicalChemistryDeltaCheckEngine;

        // 1. Hitung RCV untuk Potassium (CV_A = 1.5, CV_I = 4.6)
        $rcv = $engine->calculateReferenceChangeValue(1.5, 4.6, 1.96);
        $this->assertGreaterThan(10.0, $rcv);

        // 2. Evaluasi perubahan normal
        $now = Carbon::parse('2026-11-09 10:00:00');
        $prior = Carbon::parse('2026-11-08 10:00:00');
        $resultNormal = $engine->evaluateDelta('POTASSIUM', 4.2, 4.0, $now, $prior);
        $this->assertFalse($resultNormal['is_delta_exceeded']);
        $this->assertEquals('NORMAL', $resultNormal['severity']);

        // 3. Evaluasi pergeseran ekstrim dalam waktu singkat -> Flag suspect misidentification
        $resultMismatch = $engine->evaluateDelta('POTASSIUM', 6.8, 3.5, $now, $prior);
        $this->assertTrue($resultMismatch['is_delta_exceeded']);
        $this->assertTrue($resultMismatch['suspect_misidentification']);
        $this->assertEquals('CRITICAL_MISMATCH', $resultMismatch['severity']);
    }

    public function test_blood_gas_interpreter_computes_acid_base_and_horowitz_index(): void
    {
        $interpreter = new BloodGasElectrolyteAcidBaseInterpreter;

        // Kasus: Asidosis Metabolik HAGMA dengan kompensasi respirasi adekuat (DKA)
        // pH 7.20, pCO2 25, pO2 95, HCO3 10, Na 138, Cl 98, FiO2 0.21
        $eval = $interpreter->interpret(
            pH: 7.20,
            pCO2: 25.0,
            pO2: 95.0,
            hco3: 10.0,
            na: 138.0,
            cl: 98.0,
            fiO2: 0.21,
            albumin: 4.0
        );

        $this->assertStringContainsString('HIGH ANION GAP METABOLIC ACIDOSIS', $eval['primary_disorder']);
        $this->assertEquals(30.0, $eval['corrected_anion_gap']);
        $this->assertEquals('ELEVATED (HAGMA)', $eval['anion_gap_category']);
        $this->assertNotNull($eval['delta_ratio']);
        $this->assertEquals('NORMAL_OXYGENATION', $eval['oxygenation_status']);

        // Kasus ARDS Berat: pO2 55 pada FiO2 0.8 -> P/F = 68.75 <= 100
        $ardsEval = $interpreter->interpret(
            pH: 7.32,
            pCO2: 50.0,
            pO2: 55.0,
            hco3: 25.0,
            na: 140.0,
            cl: 102.0,
            fiO2: 0.8
        );
        $this->assertEquals('SEVERE_ARDS', $ardsEval['oxygenation_status']);
    }

    public function test_microbiology_culture_and_mdro_tracker(): void
    {
        $tracker = new MicrobiologyCultureAndSusceptibilityTracker;

        // 1. Interpretasi MIC tunggal
        $micRes = $tracker->interpretMic('STAPHYLOCOCCUS_AUREUS', 'OXACILLIN', 8.0);
        $this->assertEquals('RESISTANT', $micRes['interpretation']);

        // 2. Evaluasi kultur dengan MDRO MRSA
        $panel = $tracker->evaluateCulturePanel(
            specimenId: 'SPEC-MICRO-001',
            organism: 'Staphylococcus aureus',
            antibioticMicMap: [
                'OXACILLIN' => 8.0,
                'VANCOMYCIN' => 1.0,
                'LINEZOLID' => 2.0,
                'CEFOXITIN' => 16.0,
            ],
            gramStain: 'GRAM_POSITIVE_COCCI'
        );

        $this->assertTrue($panel['is_alert_organism']);
        $this->assertNotEmpty($panel['mdro_classifications']);
        $this->assertStringContainsString('MRSA', $panel['mdro_classifications'][0]);

        // 3. Matriks Antibiogram
        $matrix = $tracker->generateHospitalAntibiogramMatrix([
            ['organism' => 'STAPHYLOCOCCUS_AUREUS', 'antibiotic' => 'VANCOMYCIN', 'interpretation' => 'SUSCEPTIBLE'],
            ['organism' => 'STAPHYLOCOCCUS_AUREUS', 'antibiotic' => 'VANCOMYCIN', 'interpretation' => 'SUSCEPTIBLE'],
            ['organism' => 'STAPHYLOCOCCUS_AUREUS', 'antibiotic' => 'OXACILLIN', 'interpretation' => 'RESISTANT'],
        ]);

        $this->assertEquals(100.0, $matrix['STAPHYLOCOCCUS_AUREUS']['VANCOMYCIN']['percent_susceptible']);
        $this->assertEquals(0.0, $matrix['STAPHYLOCOCCUS_AUREUS']['OXACILLIN']['percent_susceptible']);
    }

    public function test_histopathology_traceability_and_autostainer_engine(): void
    {
        $histoService = new HistopathologyTissueBlockTraceabilityService;

        // 1. Cold Ischemia
        $resect = Carbon::parse('2026-11-09 08:00:00');
        $fixStart = Carbon::parse('2026-11-09 08:35:00');
        $ischemia = $histoService->evaluateColdIschemia($resect, $fixStart);
        $this->assertTrue($ischemia['is_within_guidelines']);
        $this->assertEquals('LOW', $ischemia['risk_level']);

        // 2. Fixation Duration
        $procStart = Carbon::parse('2026-11-09 20:35:00');
        $fixEval = $histoService->evaluateFixationDuration($fixStart, $procStart);
        $this->assertTrue($fixEval['is_acceptable']);
        $this->assertEquals('ADEQUATE', $fixEval['status']);

        // 3. Manifest Block & Slide
        $manifest = $histoService->generateBlockAndSlideManifest('PA-2026-0099', 'A', 2, ['HE', 'IHC_ER']);
        $this->assertEquals(2, $manifest['total_blocks']);
        $this->assertEquals(4, $manifest['total_slides']);

        // 4. Stainer Robot Engine
        $stainer = new AutomatedPathologySlideStainingEngine;
        $plan = $stainer->planStainingRun('HEMATOXYLIN_EOSIN', 30);
        $this->assertTrue($plan['is_safe_to_execute']);
        $this->assertGreaterThan(10, $plan['total_steps']);

        $quality = $stainer->evaluateStainingQuality(true, true, true, true);
        $this->assertTrue($quality['is_diagnostic_quality']);
        $this->assertEquals(100, $quality['stain_quality_score']);
    }

    public function test_dicom_part10_parser_and_modality_worklist(): void
    {
        $parser = new DicomPart10HeaderParser;

        // Buat dummy stream biner DICOM Part 10 valid
        $preamble = str_repeat("\0", 128);
        $magic = 'DICM';

        // Tag (0002,0010) Transfer Syntax UID: Explicit VR Little Endian (1.2.840.10008.1.2.1)
        $tagGroup2 = pack('v', 0x0002);
        $tagElem10 = pack('v', 0x0010);
        $vrUi = 'UI';
        $uidStr = '1.2.840.10008.1.2.1';
        $valLen = strlen($uidStr);
        $lenPack = pack('v', $valLen);

        // Tag (0008,0060) Modality: 'CT'
        $tagGroup8 = pack('v', 0x0008);
        $tagElem60 = pack('v', 0x0060);
        $vrCs = 'CS';
        $modalityStr = 'CT';
        $modalityLen = pack('v', strlen($modalityStr));

        $binaryStream = $preamble.$magic.$tagGroup2.$tagElem10.$vrUi.$lenPack.$uidStr.$tagGroup8.$tagElem60.$vrCs.$modalityLen.$modalityStr;

        $this->assertTrue($parser->isValidDicomStream($binaryStream));
        $parsed = $parser->parseDicomStream($binaryStream);
        $this->assertTrue($parsed['is_valid_dicom']);
        $this->assertEquals('CT', $parsed['modality']);
        $this->assertEquals('1.2.840.10008.1.2.1', $parsed['transfer_syntax_uid']);

        // Modality Worklist Scheduler
        $scheduler = new RadiologyModalityWorklistScheduler;
        $sps = $scheduler->createScheduledProcedureStep(
            accessionNumber: 'ACC-CT-999',
            patientMrn: 'MRN-105-001',
            patientName: 'John Doe',
            modality: 'CT',
            stationAeTitle: 'CT_SCANNER_01',
            procedureDescription: 'CT Thorax Contrast',
            scheduledDateTime: Carbon::parse('2026-11-09 14:00:00')
        );

        $this->assertEquals('SCHEDULED', $sps['sps_status']);
        $this->assertEquals('CT_SCANNER_01', $sps['station_ae_title']);

        // Query C-FIND
        $cfindMatches = $scheduler->handleCFindQuery([$sps], ['modality' => 'CT', 'station_ae_title' => 'CT_SCANNER_01']);
        $this->assertCount(1, $cfindMatches);

        // Transition status
        $inProgress = $scheduler->transitionSpsStatus($sps, 'IN_PROGRESS');
        $this->assertEquals('IN_PROGRESS', $inProgress['sps_status']);
    }

    public function test_external_proficiency_testing_and_calibration_tracker(): void
    {
        // 1. EQAS Evaluator
        $eqas = new LaboratoryExternalProficiencyTestingEvaluator;
        $sdiEval = $eqas->evaluateSdi('GLUCOSE', 105.0, 100.0, 5.0);
        $this->assertEquals(1.0, $sdiEval['sdi']);
        $this->assertTrue($sdiEval['is_acceptable']);
        $this->assertEquals('EXCELLENT', $sdiEval['performance_category']);

        // Youden Plot
        $youden = $eqas->evaluateYoudenTwoSamplePlot('GLUCOSE', 115.0, 100.0, 5.0, 116.0, 100.0, 5.0);
        $this->assertStringContainsString('SYSTEMATIC_ERROR', $youden['error_type']);

        // 2. Calibration Tracker
        $calibTracker = new LabAnalyzerCalibrationMaintenanceTracker;
        $points = [
            ['concentration' => 0.0, 'absorbance' => 0.010],
            ['concentration' => 50.0, 'absorbance' => 0.255],
            ['concentration' => 100.0, 'absorbance' => 0.505],
            ['concentration' => 200.0, 'absorbance' => 1.010],
        ];

        $calibCurve = $calibTracker->evaluateLinearCalibrationCurve('ANALYZER-CHEM-01', 'GLUCOSE', $points);
        $this->assertTrue($calibCurve['is_calibration_valid']);
        $this->assertGreaterThanOrEqual(0.999, $calibCurve['r_squared']);

        $patientConc = $calibTracker->calculateConcentrationFromCurve(0.505, $calibCurve['slope'], $calibCurve['intercept']);
        $this->assertEqualsWithDelta(100.0, $patientConc, 2.0);
    }

    public function test_radiology_dose_aec_and_contrast_safety_service(): void
    {
        // 1. AEC Analyzer
        $aec = new RadiologyDoseOptimizationAecAnalyzer;
        $diEval = $aec->calculateDeviationIndex(300.0, 300.0);
        $this->assertEquals(0.0, $diEval['deviation_index']);
        $this->assertEquals('TARGET_OPTIMAL', $diEval['exposure_category']);
        $this->assertTrue($diEval['alara_compliance']);

        // 2. Contrast Safety & eGFR CKD-EPI 2021
        $contrastService = new RadiologyContrastMediaSafetyScreeningService;
        $egfr = $contrastService->calculateEgfrCkdEpi2021(0.9, 45, 'MALE');
        $this->assertGreaterThan(60.0, $egfr);

        // Pasien dengan riwayat alergi & metformin
        $decision = $contrastService->screenIodinatedContrastSafety($egfr, true, true);
        $this->assertTrue($decision['is_contrast_cleared']);
        $this->assertTrue($decision['premedication_required']);

        // Gadolinium NSF screening
        $nsfEval = $contrastService->screenGadoliniumNsfRisk(25.0, 'GROUP_1');
        $this->assertFalse($nsfEval['is_permitted']);
        $this->assertEquals('VERY_HIGH_NEPHROGENIC_SYSTEMIC_FIBROSIS', $nsfEval['nsf_risk']);
    }
}
