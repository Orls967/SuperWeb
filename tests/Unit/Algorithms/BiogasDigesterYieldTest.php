<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\BiogasDigesterCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\BiogasDigesterResult;
use Modules\Shared\Domain\Algorithms\Utility\BiogasDigesterYieldCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BiogasDigesterYieldTest extends TestCase
{
    public function test_biogas_digester_yield_and_verification(): void
    {
        $calculator = new BiogasDigesterYieldCalculator;
        $verifier = new BiogasDigesterCertificateVerifier;

        // Parameter: 10,000 kg limbah organik makanan (10 ton)
        // TS = 20% (2000 bps), VS = 80% (8000 bps), degradasi = 75% (7500 bps),
        // yield = 800 L/kg VS, CH4 = 60% (6000 bps), gen efficiency = 38% (3800 bps)
        $result = $calculator->compute(
            wasteTonnageKg: 10_000,
            totalSolidsBps: 2000,
            volatileSolidsBps: 8000,
            biodegradabilityBps: 7500,
            biogasYieldLitersPerKgVs: 800,
            methaneContentBps: 6000,
            electricalEfficiencyBps: 3800
        );

        $this->assertSame(10000, $result->wasteTonnageKg);
        $this->assertSame(2000, $result->totalSolidsKg);
        $this->assertSame(1600, $result->volatileSolidsKg);
        $this->assertSame(1200, $result->degradedVsKg);
        $this->assertSame(960, $result->biogasYieldM3);
        $this->assertSame(576, $result->methaneM3);
        $this->assertGreaterThan(0, $result->thermalEnergyMj);
        $this->assertGreaterThan(0, $result->electricGrossKwh);
        $this->assertGreaterThan(0, $result->avoidedEmissionCo2eKg);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_biogas_tampered_hash_throws(): void
    {
        $calculator = new BiogasDigesterYieldCalculator;
        $verifier = new BiogasDigesterCertificateVerifier;

        $result = $calculator->compute(10_000, 2000, 8000, 7500, 800, 6000, 3800);

        $tampered = new BiogasDigesterResult(
            wasteTonnageKg: $result->wasteTonnageKg,
            totalSolidsKg: $result->totalSolidsKg,
            volatileSolidsKg: $result->volatileSolidsKg,
            degradedVsKg: $result->degradedVsKg,
            biogasYieldM3: $result->biogasYieldM3,
            methaneM3: $result->methaneM3,
            thermalEnergyMj: $result->thermalEnergyMj,
            electricGrossKwh: $result->electricGrossKwh,
            avoidedEmissionCo2eKg: $result->avoidedEmissionCo2eKg,
            certificateHash: 'corrupted-hash',
            canonicalPayload: $result->canonicalPayload,
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
