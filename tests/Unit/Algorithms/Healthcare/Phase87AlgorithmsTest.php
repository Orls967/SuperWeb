<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms\Healthcare;

use Modules\Hospital\Domain\Algorithms\BedAssignmentOptimizer;
use Modules\Hospital\Domain\Algorithms\BedAssignmentCertificate;
use Modules\Hospital\Domain\Algorithms\BedAssignmentCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\EarlyWarningScoreCalculator;
use Modules\Hospital\Domain\Algorithms\News2CalculationCertificate;
use Modules\Hospital\Domain\Algorithms\News2CertificateVerifier;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayDelayDetector;
use Modules\Hospital\Domain\Algorithms\ClinicalPathwayScheduleCertificate;
use Modules\Hospital\Domain\Algorithms\PathwayScheduleCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyIndicators;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyCertificate;
use Modules\Hospital\Domain\Algorithms\BedEfficiencyCertificateVerifier;
use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationPlanner;
use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationCertificate;
use Modules\Hospital\Domain\Algorithms\CodeBlueCertificateVerifier;
use RuntimeException;
use Tests\TestCase;

class Phase87AlgorithmsTest extends TestCase
{
    /**
     * Uji B1: BedAssignmentOptimizer sesuai vektor prompt.
     */
    public function test_b1_bed_assignment_optimizer_vector_and_verifier(): void
    {
        $optimizer = new BedAssignmentOptimizer();

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
        $calculator = new EarlyWarningScoreCalculator();

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
        $detector = new ClinicalPathwayDelayDetector();

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
        $indicators = new BedEfficiencyIndicators();

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
        $planner = new CodeBlueEscalationPlanner();

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

        $this->expectException(RuntimeException::class);
        CodeBlueCertificateVerifier::verify($corruptCert);
    }
}
