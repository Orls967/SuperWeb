<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\CatastrophicCaseClaimEngine;
use PHPUnit\Framework\TestCase;

class CatastrophicCaseClaimEngineTest extends TestCase
{
    private CatastrophicCaseClaimEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CatastrophicCaseClaimEngine;
    }

    public function test_evaluates_catastrophic_case_when_deficit_exceeds_stop_loss_threshold(): void
    {
        // Biaya RS Rp 200.000.000, BPJS mengganti Rp 80.000.000 -> Defisit Rp 120.000.000 (> Rp 75.000.000)
        $result = $this->engine->evaluateCatastrophicFinancials(
            'CABG_SURGERY',
            200_000_000,
            80_000_000,
            6
        );

        $this->assertSame('CABG_SURGERY', $result['case_type']);
        $this->assertSame(120_000_000, $result['net_hospital_deficit_idr']);
        $this->assertTrue($result['is_stop_loss_triggered']);

        // Excess deficit = 120jt - 75jt = 45jt. 85% dari 45jt = 38.250.000
        $this->assertSame(38_250_000, $result['stop_loss_reinsurance_claim_idr']);
        $this->assertSame(81_750_000, $result['uncovered_hospital_loss_idr']);
    }

    public function test_non_triggered_stop_loss_when_deficit_below_threshold(): void
    {
        // Biaya RS Rp 90.000.000, BPJS mengganti Rp 50.000.000 -> Defisit Rp 40.000.000 (<= Rp 75jt)
        $result = $this->engine->evaluateCatastrophicFinancials(
            'VALVE_REPLACEMENT',
            90_000_000,
            50_000_000,
            3
        );

        $this->assertFalse($result['is_stop_loss_triggered']);
        $this->assertSame(0, $result['stop_loss_reinsurance_claim_idr']);
        $this->assertSame(40_000_000, $result['uncovered_hospital_loss_idr']);
    }
}
