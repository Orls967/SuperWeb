<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\Smelter\SmelterEnergyReading;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterFeedParameters;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterPyrometallurgicalEnergyBalanceSolver;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterPyrometallurgicalEnergyBalanceVerifier;
use Tests\TestCase;

class SmelterEnergyBalanceTest extends TestCase
{
    public function test_smelter_solver_computes_thermodynamic_mass_and_heat_balance(): void
    {
        $solver = new SmelterPyrometallurgicalEnergyBalanceSolver;
        $verifier = new SmelterPyrometallurgicalEnergyBalanceVerifier;

        $feed = new SmelterFeedParameters(
            oreFeedWmt: 150.0,
            oreMoisturePct: 32.0,
            niGradePct: 1.85,
            feGradePct: 18.0,
            sio2GradePct: 42.0,
            mgoGradePct: 22.0,
            al2o3GradePct: 2.5,
            coalReductantTons: 16.5,
            coalFixedCarbonPct: 82.0,
            silicaFluxTons: 9.5,
            limestoneFluxTons: 4.0,
            targetTappingTemperatureCelsius: 1530.0
        );

        $energy = new SmelterEnergyReading(
            meteredElectricalPowerMwh: 48.0,
            gridEmissionFactorTco2PerMwh: 0.7900,
            coalEmissionFactorTco2PerTon: 2.4200,
            dieselFuelLiters: 150.0
        );

        $result = $solver->solve($feed, $energy, 92.5);

        // Assert mass balance
        $this->assertEquals(102.0, $result->dryFeedTons);
        $this->assertGreaterThan(12.0, $result->metalTappedTons);
        $this->assertLessThan(25.0, $result->metalTappedTons);
        $this->assertGreaterThan(50.0, $result->slagTappedTons);
        $this->assertGreaterThan(10.0, $result->metalNiGradePct);

        // Assert thermal feasibility & emissions
        $this->assertTrue($result->isThermallyFeasible);
        $this->assertGreaterThan(0.0, $result->thermalEfficiencyPct);
        $this->assertGreaterThan(0.0, $result->grossEmissionsTco2e);
        $this->assertNotEmpty($result->verificationHash);

        // Assert verifier passes
        $this->assertTrue($verifier->verify($result, $feed));
    }

    public function test_feed_validation_rejects_negative_ore_feed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SmelterFeedParameters(
            oreFeedWmt: -10.0,
            oreMoisturePct: 30.0,
            niGradePct: 1.8,
            feGradePct: 18.0,
            sio2GradePct: 40.0,
            mgoGradePct: 20.0,
            al2o3GradePct: 2.0,
            coalReductantTons: 10.0,
            coalFixedCarbonPct: 80.0,
            silicaFluxTons: 5.0,
            limestoneFluxTons: 2.0
        );
    }
}
