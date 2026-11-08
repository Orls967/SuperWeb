<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Modules\Marine\Domain\Algorithms\ExportAssayToleranceChecker;
use Tests\TestCase;

class ExportAssayToleranceTest extends TestCase
{
    protected ExportAssayToleranceChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new ExportAssayToleranceChecker;
    }

    public function test_clean_assay_passes_fda_and_eu_regulations_with_prime_grade(): void
    {
        $cleanResults = [
            'chloramphenicol_ppb' => 0.0,
            'nitrofuran_aoz_ppb' => 0.0,
            'histamine_ppm' => 4.5,
            'cadmium_ppm' => 0.05,
            'salmonella_present' => false,
            'vibrio_cholerae_present' => false,
        ];

        $res = $this->checker->evaluateAssay('US_FDA', $cleanResults);

        $this->assertTrue($res['is_eligible_for_export']);
        $this->assertEquals('GRADE_A_PRIME', $res['health_certificate_grade']);
        $this->assertEquals('SPS_EXPORT_PERMIT_APPROVED', $res['quarantine_release_status']);
    }

    public function test_positive_chloramphenicol_violates_zero_tolerance_and_quarantines(): void
    {
        $taintedResults = [
            'chloramphenicol_ppb' => 0.8, // Batas 0.3 ppb
            'salmonella_present' => false,
        ];

        $res = $this->checker->evaluateAssay('EU', $taintedResults);

        $this->assertFalse($res['is_eligible_for_export']);
        $this->assertEquals('REJECTED', $res['health_certificate_grade']);
        $this->assertEquals('QUARANTINE_DETAINED_DESTRUCTION_REQUIRED', $res['quarantine_release_status']);
        $this->assertNotEmpty($res['violations']);
    }
}
