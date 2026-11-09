<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\MultiTierBenefitCoordinationEngine;
use Tests\TestCase;

class MultiTierBenefitCoordinationEngineTest extends TestCase
{
    private MultiTierBenefitCoordinationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MultiTierBenefitCoordinationEngine();
    }

    public function test_sublimit_capping_and_coinsurance_calculation(): void
    {
        $charges = [
            [
                'category' => 'BED_DAY',
                'subtotal_idr' => 6_000_000,
                'quantity' => 3, // Daily limit 1.5jt * 3 = 4.5jt cap
            ],
            [
                'category' => 'PROCEDURE',
                'subtotal_idr' => 20_000_000,
                'quantity' => 1, // Coinsurance 10% = 2jt
            ],
            [
                'category' => 'PHARMACY',
                'subtotal_idr' => 5_000_000,
                'quantity' => 1,
            ],
        ];

        $bpjsSettled = 12_000_000;
        $deductible = 1_000_000;

        $result = $this->engine->adjudicatePolicySublimits(
            itemizedCharges: $charges,
            bpjsPrimaryContributionIdr: $bpjsSettled,
            deductibleIdr: $deductible
        );

        $this->assertSame(31_000_000, $result['total_gross_billed_idr']);
        $this->assertSame(12_000_000, $result['bpjs_primary_settled_idr']);
        $this->assertGreaterThan(0, $result['total_sublimit_excess_idr']);
        $this->assertGreaterThan(0, $result['total_coinsurance_idr']);
        $this->assertGreaterThan(0, $result['final_private_insurer_payable_idr']);
        $this->assertGreaterThanOrEqual(0, $result['patient_out_of_pocket_idr']);

        // Verifikasi konservasi saldo matematis
        $totalCovered = $result['bpjs_primary_settled_idr'] + $result['final_private_insurer_payable_idr'] + $result['patient_out_of_pocket_idr'];
        $this->assertSame($result['total_gross_billed_idr'], $totalCovered);
    }
}
