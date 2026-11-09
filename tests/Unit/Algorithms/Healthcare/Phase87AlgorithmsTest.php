<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Healthcare;

use Modules\Hospital\Domain\Algorithms\AntibioticStewardshipRuleEngine;
use Modules\Hospital\Domain\Algorithms\BedAssignmentCertificate;
use Modules\Hospital\Domain\Algorithms\BedAssignmentCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\BedAssignmentOptimizer;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyCertificate;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyIndicators;
use Modules\Hospital\Domain\Algorithms\ClinicalNutritionRiskCalculator;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayDelayDetector;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayScheduleCertificate;
use Modules\Hospital\Domain\Algorithms\CodeBlueCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationCertificate;
use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationPlanner;
use Modules\Hospital\Domain\Algorithms\CriticalIncidentReplayEngine;
use Modules\Hospital\Domain\Algorithms\EarlyWarningScoreCalculator;
use Modules\Hospital\Domain\Algorithms\FallRiskAssessmentEngine;
use Modules\Hospital\Domain\Algorithms\HospitalInfectionControlRiskScorer;
use Modules\Hospital\Domain\Algorithms\News2CalculationCertificate;
use Modules\Hospital\Domain\Algorithms\News2CertificateVerifier;
use Modules\Hospital\Domain\Algorithms\PaediatricEarlyWarningScoreCalculator;
use Modules\Hospital\Domain\Algorithms\PathwayScheduleCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\PatientDischargeReadinessScorer;
use Modules\Hospital\Domain\Algorithms\SurgicalApgarScoreCalculator;
use Modules\Hospital\Domain\Algorithms\VitalSignTrendAnalyzer;
use RuntimeException;
use Tests\TestCase;

class Phase87AlgorithmsTest extends TestCase
{
    /**
     * Uji B1: BedAssignmentOptimizer sesuai vektor prompt.
     */
    public function test_b1_bed_assignment_optimizer_vector_and_verifier(): void
    {
        $optimizer = new BedAssignmentOptimizer;

        $beds = [
            ['bed_code' => '101', 'bed_class' => 'ICU'],
            ['bed_code' => '102', 'bed_class' => 'ICU'],
            ['bed_code' => '201', 'bed_class' => 'ISOLATION'],
            ['bed_code' => '301', 'bed_class' => 'CLASS_1'],
            ['bed_code' => '401', 'bed_class' => 'CLASS_2'],
            ['bed_code' => '501', 'bed_class' => 'CLASS_3'],
        ];

        $patients = [
            ['id' => 'P1', 'required_class' => 'ICU', 'priority' => 0, 'waiting_minutes' => 10],
            ['id' => 'P2', 'required_class' => 'ICU', 'priority' => 1, 'waiting_minutes' => 90],
            ['id' => 'P3', 'required_class' => 'ICU', 'priority' => 0, 'waiting_minutes' => 5],
            ['id' => 'P4', 'required_class' => 'ISOLATION', 'priority' => 1, 'waiting_minutes' => 30],
            ['id' => 'P5', 'required_class' => 'CLASS_3', 'priority' => 2, 'waiting_minutes' => 200],
            ['id' => 'P6', 'required_class' => 'CLASS_3', 'priority' => 2, 'waiting_minutes' => 100],
            ['id' => 'P7', 'required_class' => 'CLASS_2', 'priority' => 2, 'waiting_minutes' => 50],
        ];

        $result = $optimizer->optimize($patients, $beds);

        // Urutan pasien: [P1, P3, P2, P4, P5, P6, P7]
        $this->assertEquals(['P1', 'P3', 'P2', 'P4', 'P5', 'P6', 'P7'], $result->sortedPatientIds());

        // Alokasi:
        // P1->101, P3->102, P2->antre, P4->201, P5->501, P6->401 (upgrade), P7->301 (upgrade)
        $this->assertEquals('101', $result->findAssignment('P1')['bed_code']);
        $this->assertEquals('102', $result->findAssignment('P3')['bed_code']);
        $this->assertTrue($result->isUnassigned('P2'));
        $this->assertEquals('201', $result->findAssignment('P4')['bed_code']);
        $this->assertEquals('501', $result->findAssignment('P5')['bed_code']);
        $this->assertEquals('401', $result->findAssignment('P6')['bed_code']);
        $this->assertTrue($result->findAssignment('P6')['is_upgrade']);
        $this->assertEquals('301', $result->findAssignment('P7')['bed_code']);
        $this->assertTrue($result->findAssignment('P7')['is_upgrade']);

        // Sertifikat rusak -> exception
        $corruptCert = new BedAssignmentCertificate(
            sortedPatientIds: $result->sortedPatientIds(),
            assignments: $result->assignments,
            unassigned: $result->unassigned,
            generatedAt: time(),
            customSha256: 'corrupted-hash-bed'
        );

        $this->expectException(RuntimeException::class);
        BedAssignmentCertificateVerifier::verify($corruptCert);
    }

    /**
     * Uji B2: EarlyWarningScoreCalculator sesuai 5 vektor prompt.
     */
    public function test_b2_early_warning_score_calculator_vector_and_verifier(): void
    {
        $calculator = new EarlyWarningScoreCalculator;

        // 1. (18, 97, tidak, 120, 72, A, 368) -> 0 RENDAH
        $res1 = $calculator->calculate(18, 97, false, 120, 72, 'A', 368);
        $this->assertEquals(0, $res1->totalScore);
        $this->assertEquals('RENDAH', $res1->riskCategory);

        // 2. (22, 94, ya, 105, 105, A, 385) -> 2+1+2+1+1+0+1 = 8 TINGGI
        $res2 = $calculator->calculate(22, 94, true, 105, 105, 'A', 385);
        $this->assertEquals(8, $res2->totalScore);
        $this->assertEquals('TINGGI', $res2->riskCategory);

        // 3. (14, 96, tidak, 88, 70, A, 365) -> 3 RENDAH_SEDANG (sistolik <= 90 = 3)
        $res3 = $calculator->calculate(14, 96, false, 88, 70, 'A', 365);
        $this->assertEquals(3, $res3->totalScore);
        $this->assertEquals('RENDAH_SEDANG', $res3->riskCategory);

        // 4. (24, 92, tidak, 100, 95, A, 370) -> 2+2+0+2+1 = 7 TINGGI
        $res4 = $calculator->calculate(24, 92, false, 100, 95, 'A', 370);
        $this->assertEquals(7, $res4->totalScore);
        $this->assertEquals('TINGGI', $res4->riskCategory);

        // 5. (20, 98, tidak, 130, 80, C, 372) -> 3 RENDAH_SEDANG (kesadaran bukan A = 3)
        $res5 = $calculator->calculate(20, 98, false, 130, 80, 'C', 372);
        $this->assertEquals(3, $res5->totalScore);
        $this->assertEquals('RENDAH_SEDANG', $res5->riskCategory);

        // Sertifikat rusak -> exception
        $corruptCert = new News2CalculationCertificate(
            totalScore: 8,
            riskCategory: 'TINGGI',
            breakdown: $res2->breakdown,
            hasSingleThree: false,
            generatedAt: time(),
            customSha256: 'corrupted-news2-hash'
        );

        $this->expectException(RuntimeException::class);
        News2CertificateVerifier::verify($corruptCert);
    }

    /**
     * Uji B3: ClinicalPathwayDelayDetector sesuai vektor prompt.
     */
    public function test_b3_clinical_pathway_delay_detector_vector_and_verifier(): void
    {
        $detector = new ClinicalPathwayDelayDetector;

        $orders = [
            [
                'id' => 'LAB',
                'order_type' => 'LABORATORY',
                'planned_start_minute' => 480, // 08:00
                'duration_minute' => 60,
                'prerequisites' => [],
                'actual_start_minute' => 505, // 08:25 (terlambat 25 menit)
            ],
            [
                'id' => 'RAD',
                'order_type' => 'RADIOLOGY',
                'planned_start_minute' => 540, // 09:00
                'duration_minute' => 45,
                'prerequisites' => ['LAB'],
            ],
            [
                'id' => 'PROC',
                'order_type' => 'PROCEDURE',
                'planned_start_minute' => 600, // 10:00
                'duration_minute' => 90,
                'prerequisites' => ['RAD'],
            ],
        ];

        $result = $detector->detectDelays($orders, 15);

        // Alert [LAB, 25 menit]
        $this->assertNotNull($result->alert);
        $this->assertEquals('LAB', $result->alert['order_id']);
        $this->assertEquals(25, $result->alert['delay_minutes']);

        // Proyeksi mulai RAD 09:25 (565), PROC 10:10 (610)
        $this->assertEquals(565, $result->projectedStarts['RAD']);
        $this->assertEquals(610, $result->projectedStarts['PROC']);

        // Sertifikat rusak -> exception
        $corruptCert = new ClinicalPathwayScheduleCertificate(
            orderCount: 3,
            alert: $result->alert,
            projectedStarts: $result->projectedStarts,
            projectedCompletions: $result->projectedCompletions,
            generatedAt: time(),
            customSha256: 'corrupted-pathway-hash'
        );

        $this->expectException(RuntimeException::class);
        PathwayScheduleCertificateVerifier::verify($corruptCert);
    }

    /**
     * Uji B4: BedEfficiencyIndicators sesuai 2 vektor Barber-Johnson.
     */
    public function test_b4_bed_efficiency_indicators_vector_and_verifier(): void
    {
        $indicators = new BedEfficiencyIndicators;

        // 1. 200 bed, 30 hari, 4.800 hari-rawat, 1.000 keluar -> BOR 8.000, ALOS 4.800, TOI 1.200, BTO 5.000
        $res1 = $indicators->calculate(200, 30, 4800, 1000);
        $this->assertEquals(8000, $res1->borBps);
        $this->assertEquals(4800, $res1->alosMilli);
        $this->assertEquals(1200, $res1->toiMilli);
        $this->assertEquals(5000, $res1->btoMilli);

        // 2. 100 bed, 30 hari, 1.500 hari-rawat, 600 keluar -> BOR 5.000, ALOS 2.500, TOI 2.500, BTO 6.000
        $res2 = $indicators->calculate(100, 30, 1500, 600);
        $this->assertEquals(5000, $res2->borBps);
        $this->assertEquals(2500, $res2->alosMilli);
        $this->assertEquals(2500, $res2->toiMilli);
        $this->assertEquals(6000, $res2->btoMilli);

        // Sertifikat rusak -> exception
        $corruptCert = new BedEfficiencyCertificate(
            bedCount: 200,
            periodDays: 30,
            inpatientDays: 4800,
            discharges: 1000,
            borBps: 8000,
            alosMilli: 4800,
            toiMilli: 1200,
            btoMilli: 5000,
            generatedAt: time(),
            customSha256: 'corrupted-bed-efficiency-hash'
        );

        $this->expectException(RuntimeException::class);
        BedEfficiencyCertificateVerifier::verify($corruptCert);
    }

    /**
     * Uji B5: CodeBlueEscalationPlanner sesuai vektor prompt.
     */
    public function test_b5_code_blue_escalation_planner_vector_and_verifier(): void
    {
        $planner = new CodeBlueEscalationPlanner;

        $readings = [
            ['minute' => 0, 'spo2' => 90, 'heart_rate' => 80],
            ['minute' => 1, 'spo2' => 83, 'heart_rate' => 100], // breach 1
            ['minute' => 2, 'spo2' => 82, 'heart_rate' => 100], // breach 2 -> NAIK
            ['minute' => 3, 'spo2' => 81, 'heart_rate' => 100],
            ['minute' => 4, 'spo2' => 80, 'heart_rate' => 100],
        ];

        // 1. Naik pada menit 2; ack menit 3 -> [PERAWAT@2]
        $resAck3 = $planner->plan($readings, 3);
        $this->assertTrue($resAck3->isTriggered);
        $this->assertEquals(2, $resAck3->triggerMinute);
        $this->assertEquals(['PERAWAT@2'], $resAck3->escalations);

        // 2. Tanpa ack -> [PERAWAT@2, DOKTER@4, TIM@7]
        $resNoAck = $planner->plan($readings, null);
        $this->assertEquals(['PERAWAT@2', 'DOKTER@4', 'TIM@7'], $resNoAck->escalations);

        // 3. Ack menit 5 -> [PERAWAT@2, DOKTER@4]
        $resAck5 = $planner->plan($readings, 5);
        $this->assertEquals(['PERAWAT@2', 'DOKTER@4'], $resAck5->escalations);

        // 4. Pelanggaran menit 0 dan 5 saja -> tidak naik
        $readingsSparse = [
            ['minute' => 0, 'spo2' => 83, 'heart_rate' => 80],
            ['minute' => 1, 'spo2' => 92, 'heart_rate' => 80],
            ['minute' => 2, 'spo2' => 92, 'heart_rate' => 80],
            ['minute' => 3, 'spo2' => 92, 'heart_rate' => 80],
            ['minute' => 4, 'spo2' => 92, 'heart_rate' => 80],
            ['minute' => 5, 'spo2' => 83, 'heart_rate' => 80],
        ];
        $resSparse = $planner->plan($readingsSparse);
        $this->assertFalse($resSparse->isTriggered);

        // Sertifikat rusak -> exception
        $corruptCert = new CodeBlueEscalationCertificate(
            isTriggered: true,
            triggerMinute: 2,
            escalations: ['PERAWAT@2'],
            generatedAt: time(),
            customSha256: 'corrupted-code-blue-hash'
        );

        $caught = false;
        try {
            CodeBlueCertificateVerifier::verify($corruptCert);
        } catch (RuntimeException $e) {
            $caught = true;
        }
        $this->assertTrue($caught);
    }

    /**
     * Uji B6: HospitalInfectionControlRiskScorer.
     */
    public function test_b6_infection_control_risk_scorer_and_verifier(): void
    {
        $scorer = new HospitalInfectionControlRiskScorer;

        $profile = [
            'has_invasive_device' => true,
            'device_days' => 8,
            'is_immunocompromised' => true,
            'prior_multidrug_resistant_organism' => true,
            'has_open_wound' => false,
            'current_antibiotic_days' => 10,
            'respiratory_symptoms' => false,
            'diarrhea_active' => false,
            'icu_stay' => true,
        ];

        $res = $scorer->evaluate($profile);
        $this->assertGreaterThanOrEqual(60, $res['risk_score']);
        $this->assertEquals('VERY_HIGH', $res['risk_category']);
        $this->assertTrue($scorer->verifyCertificate($res, $profile));

        // Tamper test
        $tampered = $res;
        $tampered['risk_score'] = 10;
        $this->assertFalse($scorer->verifyCertificate($tampered, $profile));
    }

    /**
     * Uji B7: PatientDischargeReadinessScorer.
     */
    public function test_b7_patient_discharge_readiness_scorer_and_verifier(): void
    {
        $scorer = new PatientDischargeReadinessScorer;

        $readyCriteria = [
            'vitals_stable_24h' => true,
            'oral_intake_adequate' => true,
            'independent_or_assisted_ambulation' => true,
            'pain_controlled' => true,
            'iv_medications_converted_to_oral' => true,
            'caregiver_trained' => true,
            'transportation_arranged' => true,
            'pending_critical_lab_results' => false,
            'news2_score' => 1,
        ];

        $evalReady = $scorer->evaluate($readyCriteria);
        $this->assertTrue($evalReady['is_ready_for_discharge']);
        $this->assertEquals(100, $evalReady['readiness_score_pct']);
        $this->assertTrue($scorer->verifyCertificate($evalReady, $readyCriteria));

        $blockedCriteria = $readyCriteria;
        $blockedCriteria['pending_critical_lab_results'] = true;
        $evalBlocked = $scorer->evaluate($blockedCriteria);
        $this->assertFalse($evalBlocked['is_ready_for_discharge']);
        $this->assertContains('Terdapat hasil pemeriksaan laboratorium kritis yang belum diverifikasi DPJP', $evalBlocked['blocking_reasons']);
    }

    /**
     * Uji B8: VitalSignTrendAnalyzer.
     */
    public function test_b8_vital_sign_trend_analyzer_and_verifier(): void
    {
        $analyzer = new VitalSignTrendAnalyzer;

        // Dekompensasi: HR naik drastis (80->95->110->130), SBP turun drastis (120->105->90->75)
        $history = [
            ['timestamp' => '2026-11-09 10:00:00', 'heart_rate' => 80, 'systolic_bp' => 120, 'respiratory_rate' => 16, 'spo2' => 98.0, 'temperature' => 37.0],
            ['timestamp' => '2026-11-09 10:15:00', 'heart_rate' => 95, 'systolic_bp' => 105, 'respiratory_rate' => 20, 'spo2' => 96.0, 'temperature' => 37.2],
            ['timestamp' => '2026-11-09 10:30:00', 'heart_rate' => 110, 'systolic_bp' => 90, 'respiratory_rate' => 24, 'spo2' => 93.0, 'temperature' => 37.5],
            ['timestamp' => '2026-11-09 10:45:00', 'heart_rate' => 130, 'systolic_bp' => 75, 'respiratory_rate' => 28, 'spo2' => 89.0, 'temperature' => 38.0],
        ];

        $result = $analyzer->analyze($history);
        $this->assertTrue($result['is_deteriorating_rapidly']);
        $this->assertGreaterThan(0, $result['hr_slope']);
        $this->assertLessThan(0, $result['sbp_slope']);
        $this->assertTrue($analyzer->verifyCertificate($result, $history));
    }

    /**
     * Uji B9: CriticalIncidentReplayEngine.
     */
    public function test_b9_critical_incident_replay_engine_and_verifier(): void
    {
        $engine = new CriticalIncidentReplayEngine;

        // Urutan hash berantai
        $e0Prev = 'GENESIS_CRITICAL_INCIDENT_HASH_2026';
        $e0Ser = json_encode(['sequence' => 0, 'event_type' => 'CODE_BLUE_ACTIVATED', 'timestamp' => '2026-11-09 12:00:00', 'actor_id' => 1, 'actor_role' => 'nurse', 'payload' => [], 'prev_hash' => $e0Prev], JSON_UNESCAPED_SLASHES);
        $h0 = hash('sha256', $e0Ser);

        $e1Ser = json_encode(['sequence' => 1, 'event_type' => 'CPR_INITIATED', 'timestamp' => '2026-11-09 12:01:00', 'actor_id' => 2, 'actor_role' => 'doctor', 'payload' => [], 'prev_hash' => $h0], JSON_UNESCAPED_SLASHES);
        $h1 = hash('sha256', $e1Ser);

        $e2Ser = json_encode(['sequence' => 2, 'event_type' => 'SHOCK_DELIVERED', 'timestamp' => '2026-11-09 12:02:30', 'actor_id' => 2, 'actor_role' => 'doctor', 'payload' => ['joules' => 200], 'prev_hash' => $h1], JSON_UNESCAPED_SLASHES);

        $timeline = [
            ['sequence' => 0, 'event_type' => 'CODE_BLUE_ACTIVATED', 'timestamp' => '2026-11-09 12:00:00', 'actor_id' => 1, 'actor_role' => 'nurse', 'payload' => [], 'prev_hash' => $e0Prev],
            ['sequence' => 1, 'event_type' => 'CPR_INITIATED', 'timestamp' => '2026-11-09 12:01:00', 'actor_id' => 2, 'actor_role' => 'doctor', 'payload' => [], 'prev_hash' => $h0],
            ['sequence' => 2, 'event_type' => 'SHOCK_DELIVERED', 'timestamp' => '2026-11-09 12:02:30', 'actor_id' => 2, 'actor_role' => 'doctor', 'payload' => ['joules' => 200], 'prev_hash' => $h1],
        ];

        $audit = $engine->replay($timeline);
        $this->assertTrue($audit['is_chain_valid']);
        $this->assertEquals(60, $audit['first_cpr_interval_sec']);
        $this->assertEquals(150, $audit['first_defib_interval_sec']);
        $this->assertTrue($engine->verifyCertificate($audit));
    }

    /**
     * Uji B10: ClinicalNutritionRiskCalculator.
     */
    public function test_b10_nutrition_risk_calculator_and_verifier(): void
    {
        $calc = new ClinicalNutritionRiskCalculator;

        $inputAtRisk = [
            'bmi' => 17.5,
            'weight_loss_pct_3months' => 8.0,
            'food_intake_reduction_pct_last_week' => 50.0,
            'disease_severity_score' => 2,
            'age_years' => 72,
        ];

        $res = $calc->evaluate($inputAtRisk);
        $this->assertTrue($res['is_at_nutritional_risk']);
        $this->assertGreaterThanOrEqual(3, $res['total_nrs_score']);
        $this->assertTrue($calc->verifyCertificate($res, $inputAtRisk));

        // Tamper test
        $tampered = $res;
        $tampered['total_nrs_score'] = 0;
        $this->assertFalse($calc->verifyCertificate($tampered, $inputAtRisk));
    }

    /**
     * Uji B11: FallRiskAssessmentEngine.
     */
    public function test_b11_fall_risk_assessment_engine_and_verifier(): void
    {
        $engine = new FallRiskAssessmentEngine;

        $highRiskProfile = [
            'history_of_falling_past_3months' => true,
            'secondary_diagnosis_present' => true,
            'ambulatory_aid' => 'FURNITURE_HOLD',
            'iv_line_or_heparin_lock' => true,
            'gait_transferring' => 'IMPAIRED',
            'mental_status' => 'FORGETS_LIMITATIONS',
        ];

        $res = $engine->evaluate($highRiskProfile);
        $this->assertEquals('HIGH_RISK', $res['risk_category']);
        $this->assertGreaterThanOrEqual(45, $res['morse_score']);
        $this->assertContains('YELLOW_FALL_RISK_WRISTBAND', $res['prevention_bundle']);
        $this->assertTrue($engine->verifyCertificate($res, $highRiskProfile));
    }

    /**
     * Uji B12: AntibioticStewardshipRuleEngine.
     */
    public function test_b12_antibiotic_stewardship_rule_engine_and_verifier(): void
    {
        $engine = new AntibioticStewardshipRuleEngine;

        // 1. Reserve antibiotic tanpa persetujuan PPRA -> Ditolak
        $unapprovedReserve = [
            'antibiotic_code' => 'COLISTIN',
            'antibiotic_name' => 'Colistin 1M IU',
            'indication_type' => 'EMPIRIC',
            'duration_days' => 5,
            'culture_sensitivity_tested' => false,
            'ppra_committee_approval' => false,
        ];
        $evalUnapproved = $engine->evaluate($unapprovedReserve);
        $this->assertFalse($evalUnapproved['is_prescription_allowed']);
        $this->assertEquals('RESERVE', $evalUnapproved['aware_category']);
        $this->assertTrue($engine->verifyCertificate($evalUnapproved, $unapprovedReserve));

        // 2. Profilaksis bedah lebih dari 24 jam -> Ditolak
        $prolongedProphylaxis = [
            'antibiotic_code' => 'CEFAZOLIN',
            'antibiotic_name' => 'Cefazolin 1g',
            'indication_type' => 'PROPHYLAXIS_SURGICAL',
            'duration_days' => 3,
            'culture_sensitivity_tested' => false,
            'ppra_committee_approval' => false,
        ];
        $evalProlonged = $engine->evaluate($prolongedProphylaxis);
        $this->assertFalse($evalProlonged['is_prescription_allowed']);

        // 3. Access antibiotic indikasi empiris standar -> Disetujui
        $validAccess = [
            'antibiotic_code' => 'AMPICILLIN',
            'antibiotic_name' => 'Ampicillin 1g',
            'indication_type' => 'EMPIRIC',
            'duration_days' => 3,
            'culture_sensitivity_tested' => false,
            'ppra_committee_approval' => false,
        ];
        $evalValid = $engine->evaluate($validAccess);
        $this->assertTrue($evalValid['is_prescription_allowed']);
    }

    /**
     * Uji B13: SurgicalApgarScoreCalculator.
     */
    public function test_b13_surgical_apgar_score_calculator_and_verifier(): void
    {
        $calc = new SurgicalApgarScoreCalculator;

        $highRiskInput = [
            'estimated_blood_loss_ml' => 1200, // 0 poin
            'lowest_mean_arterial_pressure_mmhg' => 38, // 0 poin
            'lowest_heart_rate_bpm' => 50, // 0 poin
        ];

        $res = $calc->evaluate($highRiskInput);
        $this->assertEquals(0, $res['total_score']);
        $this->assertEquals('HIGH_RISK', $res['risk_tier']);
        $this->assertEquals('INTENSIVE_CARE_UNIT_VENTILATOR_MONITORING', $res['postop_disposition_recommendation']);
        $this->assertTrue($calc->verifyCertificate($res, $highRiskInput));

        $lowRiskInput = [
            'estimated_blood_loss_ml' => 80, // 3 poin
            'lowest_mean_arterial_pressure_mmhg' => 75, // 3 poin
            'lowest_heart_rate_bpm' => 90, // 4 poin
        ];
        $resLow = $calc->evaluate($lowRiskInput);
        $this->assertEquals(10, $resLow['total_score']);
        $this->assertEquals('LOW_RISK', $resLow['risk_tier']);
    }

    /**
     * Uji B14: PaediatricEarlyWarningScoreCalculator.
     */
    public function test_b14_paediatric_early_warning_score_calculator_and_verifier(): void
    {
        $calc = new PaediatricEarlyWarningScoreCalculator;

        $criticalInput = [
            'behaviour' => 'LETHARGIC_CONFUSED', // 3 poin
            'cardiovascular' => 'TACHYCARDIA_CRT_GT_4S', // 3 poin
            'respiratory' => 'SEVERE_GRUNTING_APNEA', // 3 poin
        ];

        $res = $calc->evaluate($criticalInput);
        $this->assertEquals(9, $res['total_pews_score']);
        $this->assertEquals('CRITICAL', $res['risk_category']);
        $this->assertEquals('IMMEDIATE_PAEDIATRIC_CODE_BLUE_PICU_TRANSFER', $res['escalation_action']);
        $this->assertTrue($calc->verifyCertificate($res, $criticalInput));

        // Tamper test
        $tampered = $res;
        $tampered['total_pews_score'] = 1;
        $this->assertFalse($calc->verifyCertificate($tampered, $criticalInput));
    }
}
