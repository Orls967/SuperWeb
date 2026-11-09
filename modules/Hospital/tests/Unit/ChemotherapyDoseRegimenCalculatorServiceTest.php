<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\ChemotherapyDoseRegimenCalculatorService;
use PHPUnit\Framework\TestCase;

class ChemotherapyDoseRegimenCalculatorServiceTest extends TestCase
{
    private ChemotherapyDoseRegimenCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ChemotherapyDoseRegimenCalculatorService();
    }

    public function test_mosteller_bsa_calculation(): void
    {
        // Pasien TB 160 cm, BB 60 kg -> sqrt((160 * 60) / 3600) = sqrt(2.6667) = 1.63 m2
        $bsa = $this->calculator->calculateBsaMosteller(160.0, 60.0);
        $this->assertSame(1.63, $bsa);
    }

    public function test_cockcroft_gault_creatinine_clearance(): void
    {
        // Pria 50 tahun, BB 70 kg, Serum Cr 1.0 mg/dL -> ((140 - 50) * 70) / (72 * 1) = 6300 / 72 = 87.5 mL/min
        $crCl = $this->calculator->calculateCreatinineClearance(50, 70.0, 1.0, false);
        $this->assertSame(87.5, $crCl);

        // Wanita 50 tahun, BB 70 kg, Serum Cr 1.0 mg/dL -> 87.5 * 0.85 = 74.375 -> 74.4 mL/min
        $crClFemale = $this->calculator->calculateCreatinineClearance(50, 70.0, 1.0, true);
        $this->assertSame(74.4, $crClFemale);
    }

    public function test_carboplatin_calvert_formula_calculation(): void
    {
        // Target AUC 5, CrCl 75 mL/min -> Dosis = 5 * (75 + 25) = 500.0 mg
        $dose = $this->calculator->calculateCarboplatinCalvertDose(5.0, 75.0);
        $this->assertSame(500.0, $dose);
    }

    public function test_doxorubicin_lifetime_cumulative_limit_check(): void
    {
        // BSA 1.6 m2, dosis rencana 80 mg, dosis sebelumnya 600 mg
        // Total baru 680 mg / 1.6 m2 = 425 mg/m2 (masih di bawah 450 limit)
        $safeResult = $this->calculator->verifyAnthracyclineLifetimeLimit(1.6, 80.0, 600.0);
        $this->assertTrue($safeResult['is_within_safe_lifetime_limit']);
        $this->assertSame(425.0, $safeResult['lifetime_cumulative_per_m2']);
        $this->assertNull($safeResult['warning_message']);

        // Jika dosis baru 150 mg -> Total 750 mg / 1.6 = 468.8 mg/m2 (> 450)
        $toxicResult = $this->calculator->verifyAnthracyclineLifetimeLimit(1.6, 150.0, 600.0);
        $this->assertFalse($toxicResult['is_within_safe_lifetime_limit']);
        $this->assertNotNull($toxicResult['warning_message']);
        $this->assertStringContainsString('kardiotoksisitas', $toxicResult['warning_message']);
    }
}
