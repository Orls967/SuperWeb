<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\CriticalCareOrganSupportScoringService;
use PHPUnit\Framework\TestCase;

class CriticalCareOrganSupportScoringServiceTest extends TestCase
{
    private CriticalCareOrganSupportScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CriticalCareOrganSupportScoringService();
    }

    public function test_severe_septic_shock_multiorgan_failure_score(): void
    {
        // Pasien syok sepsis: ventilasi mekanik PaO2/FiO2 120 (score 3), trombosit 35 (score 3),
        // bilirubin 2.5 (score 2), Norepinephrine (score 4), GCS 9 (score 3), kreatinin 3.8 (score 3)
        // Total SOFA = 3 + 3 + 2 + 4 + 3 + 3 = 18
        $result = $this->service->calculateSofaScore(
            120.0,
            true,
            35,
            2.5,
            60.0,
            'NOREPINEPHRINE',
            9,
            3.8,
            400.0
        );

        $this->assertSame(18, $result['total_sofa_score']);
        $this->assertSame('EXTREME_ACUITY_MULTI_ORGAN_FAILURE', $result['icu_acuity_tier']);
        $this->assertSame(80.0, $result['predicted_mortality_rate_pct']);
        $this->assertStringContainsString('Skor SOFA total 18', $result['clinical_justification_note']);
    }

    public function test_mild_icu_admission_low_score(): void
    {
        // Pasien observasi post-op stabil tanpa ventilator, MAP > 70, lab normal
        $result = $this->service->calculateSofaScore(
            420.0,
            false,
            250,
            0.8,
            85.0,
            'NONE',
            15,
            0.9,
            1800.0
        );

        $this->assertSame(0, $result['total_sofa_score']);
        $this->assertSame('LOW_ACUITY_STEPDOWN', $result['icu_acuity_tier']);
        $this->assertSame(5.0, $result['predicted_mortality_rate_pct']);
    }
}
