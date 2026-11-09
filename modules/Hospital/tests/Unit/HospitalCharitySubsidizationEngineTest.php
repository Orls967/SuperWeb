<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HospitalCharitySubsidizationEngine;
use Modules\Hospital\Domain\ValueObjects\HospitalMoney;
use PHPUnit\Framework\TestCase;

class HospitalCharitySubsidizationEngineTest extends TestCase
{
    private HospitalCharitySubsidizationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new HospitalCharitySubsidizationEngine();
    }

    public function test_full_subsidy_for_extreme_poverty_with_valid_sktm(): void
    {
        $bill = new HospitalMoney(500000000, 'IDR'); // Rp 5.000.000
        $result = $this->engine->evaluateCharitySubsidy(
            $bill,
            1200000, // Rp 1.200.000 / (4 tanggungan + 1 = 5) = Rp 240.000 / kapita (< 400.000)
            4,
            true,
            false,
            10000000 // Dana CSR cukup
        );

        $this->assertSame(HospitalCharitySubsidizationEngine::TIER_FULL_SUBSIDY, $result['tier']);
        $this->assertSame(1.0, $result['subsidy_ratio']);
        $this->assertSame(500000000, $result['subsidized_amount']->amountInMinor);
        $this->assertSame(0, $result['patient_copay']->amountInMinor);
    }

    public function test_emergency_life_saving_grants_immediate_full_coverage(): void
    {
        $bill = new HospitalMoney(1500000000, 'IDR'); // Rp 15.000.000
        $result = $this->engine->evaluateCharitySubsidy(
            $bill,
            2500000,
            2,
            false,
            true, // Emergency life threatening
            20000000
        );

        $this->assertSame(HospitalCharitySubsidizationEngine::TIER_FULL_SUBSIDY, $result['tier']);
        $this->assertSame(1500000000, $result['subsidized_amount']->amountInMinor);
    }

    public function test_rejects_non_qualifying_high_income_without_emergency(): void
    {
        $bill = new HospitalMoney(300000000, 'IDR');
        $result = $this->engine->evaluateCharitySubsidy(
            $bill,
            10000000, // Rp 10.000.000 / 2 = 5jt / kapita
            1,
            false,
            false,
            50000000
        );

        $this->assertSame(HospitalCharitySubsidizationEngine::TIER_REJECTED, $result['tier']);
        $this->assertSame(0.0, $result['subsidy_ratio']);
        $this->assertSame(300000000, $result['patient_copay']->amountInMinor);
    }
}
