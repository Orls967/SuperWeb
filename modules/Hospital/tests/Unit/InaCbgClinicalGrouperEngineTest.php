<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\InaCbgClinicalGrouperEngine;
use Tests\TestCase;

class InaCbgClinicalGrouperEngineTest extends TestCase
{
    private InaCbgClinicalGrouperEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new InaCbgClinicalGrouperEngine();
    }

    public function test_cardiac_case_with_major_complication_assigned_severity_three(): void
    {
        $result = $this->engine->adjudicateClinicalGrouper(
            primaryIcd10: 'I21.0',
            secondaryIcd10List: ['R57.0', 'J96.0', 'E11.9'], // Ada Syok Kardiogenik (MCC) & Gagal Nafas Akut (MCC)
            procedureIcd9List: ['36.06', '37.22'],
            lengthOfStayDays: 6,
            icuDays: 3,
            entitledClass: 'CLASS_1',
            hospitalType: 'B'
        );

        $this->assertSame('III', $result['severity_level']);
        $this->assertSame('I-4-10-III', $result['cbg_code']);
        $this->assertGreaterThan(0, $result['mcc_count']);
        $this->assertSame(3, $result['icu_days']);
        $this->assertGreaterThan(0, $result['icu_addon_tariff_idr']);
        $this->assertGreaterThan($result['standard_class_tariff_idr'], $result['final_adjudicated_claim_idr']);
    }

    public function test_outlier_high_trimmer_detection_for_prolonged_stay(): void
    {
        // Standar trimmer high I-4-10 adalah 14 hari
        $result = $this->engine->adjudicateClinicalGrouper(
            primaryIcd10: 'I21.0',
            secondaryIcd10List: ['I10'],
            procedureIcd9List: [],
            lengthOfStayDays: 20, // 6 hari outlier di atas batas 14 hari
            icuDays: 0,
            entitledClass: 'CLASS_2',
            hospitalType: 'B'
        );

        $this->assertSame('HIGH_OUTLIER', $result['outlier_type']);
        $this->assertSame(6, $result['outlier_days']);
        $this->assertGreaterThan(0, $result['outlier_tariff_idr']);
    }

    public function test_mild_case_without_complications_assigned_severity_one(): void
    {
        $result = $this->engine->adjudicateClinicalGrouper(
            primaryIcd10: 'O80', // Persalinan Normal
            secondaryIcd10List: [],
            procedureIcd9List: [],
            lengthOfStayDays: 2,
            icuDays: 0,
            entitledClass: 'CLASS_3',
            hospitalType: 'C'
        );

        $this->assertSame('I', $result['severity_level']);
        $this->assertSame('O-6-10-I', $result['cbg_code']);
        $this->assertSame(0, $result['outlier_days']);
        $this->assertSame('NORMAL', $result['outlier_type']);
    }
}
