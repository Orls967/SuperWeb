<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\WaterBalance;
use Modules\Shared\Domain\Algorithms\Utility\WaterBalanceCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\WaterBalanceResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WaterBalanceTest extends TestCase
{
    public function test_water_balance_satisfies_prompt_vector(): void
    {
        $algorithm = new WaterBalance;

        // Vektor prompt (m³ / unit konversi: 100.000 m³ = 100.000.000 liter atau skala m³)
        $input = 100_000;
        $billed = 78_000;
        $unbilled = 2_000;
        // under-registration 200 bps (2%) dari 78.000 = 1.560; pencurian = 440; semu = 2.000
        $apparentLosses = 2_000;
        $realLosses = 18_000;

        $result = $algorithm->calculate(
            systemInputVolumeL: $input,
            billedAuthorizedL: $billed,
            unbilledAuthorizedL: $unbilled,
            apparentLossesL: $apparentLosses,
            realLossesL: $realLosses
        );

        $this->assertSame(100_000, $result->systemInputVolumeL);
        $this->assertSame(78_000, $result->billedAuthorizedL);
        $this->assertSame(2_000, $result->unbilledAuthorizedL);
        $this->assertSame(2_000, $result->apparentLossesL);
        $this->assertSame(18_000, $result->realLossesL);
        $this->assertSame(20_000, $result->totalLossesL);
        $this->assertSame(22_000, $result->nonRevenueWaterL);
        $this->assertSame(2200, $result->nrwBasisPoints);
        $this->assertTrue($result->certified);
    }

    public function test_water_balance_corrupted_certificate_throws_exception(): void
    {
        $verifier = new WaterBalanceCertificateVerifier;

        $this->expectException(RuntimeException::class);

        // Corrupted: SIV 100_000 tapi billed 70_000 + losses 20_000 != 100_000
        $corrupted = new WaterBalanceResult(
            systemInputVolumeL: 100_000,
            billedAuthorizedL: 70_000,
            unbilledAuthorizedL: 0,
            apparentLossesL: 5_000,
            realLossesL: 15_000,
            totalLossesL: 20_000,
            nonRevenueWaterL: 30_000,
            nrwBasisPoints: 3000,
            certified: false
        );

        $verifier->verify($corrupted);
    }
}
