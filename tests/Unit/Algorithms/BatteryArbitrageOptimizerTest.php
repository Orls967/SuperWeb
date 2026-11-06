<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Shared\Domain\Algorithms\Utility\BatteryArbitrageCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Utility\BatteryArbitrageOptimizer;
use Modules\Shared\Domain\Algorithms\Utility\BatteryArbitrageResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BatteryArbitrageOptimizerTest extends TestCase
{
    public function test_battery_arbitrage_optimization_and_verification(): void
    {
        $optimizer = new BatteryArbitrageOptimizer;
        $verifier = new BatteryArbitrageCertificateVerifier;

        // Profil tarif 24 jam: 00-05 murah (Rp 900), 06-16 sedang (Rp 1.450), 17-22 peak WBP (Rp 2.200), 23 murah (Rp 900)
        $hourlyTariff = [
            900, 900, 900, 900, 900, 900,
            1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450,
            2200, 2200, 2200, 2200, 2200, 2200,
            900,
        ];

        // Baterai kapasitas 1000 kWh, max charge/discharge 250 kW, biaya degradasi Rp 150/kWh
        $result = $optimizer->optimize(
            hourlyTariffIdrPerKwh: $hourlyTariff,
            capacityKwh: 1000,
            maxPowerKw: 250,
            initialSocBps: 2000,
            minSocBps: 1500,
            maxSocBps: 9500,
            roundTripEfficiencyBps: 9000,
            degradationCostIdrPerKwh: 150
        );

        $this->assertSame(1000, $result->capacityKwh);
        $this->assertGreaterThan(0, $result->totalChargedKwh);
        $this->assertGreaterThan(0, $result->totalDischargedKwh);
        $this->assertGreaterThan(0, $result->netArbitrageProfitIdr);
        $this->assertCount(24, $result->hourlySchedule);
        $this->assertTrue($verifier->verify($result));
    }

    public function test_tampered_battery_arbitrage_certificate_fails(): void
    {
        $optimizer = new BatteryArbitrageOptimizer;
        $verifier = new BatteryArbitrageCertificateVerifier;

        $hourlyTariff = array_fill(0, 24, 1200);
        $result = $optimizer->optimize($hourlyTariff, 500, 100);

        $tampered = new BatteryArbitrageResult(
            capacityKwh: $result->capacityKwh,
            initialSocBps: $result->initialSocBps,
            finalSocBps: $result->finalSocBps,
            totalChargedKwh: $result->totalChargedKwh,
            totalDischargedKwh: $result->totalDischargedKwh,
            totalChargingCostIdr: $result->totalChargingCostIdr,
            totalDischargingRevenueIdr: $result->totalDischargingRevenueIdr,
            totalDegradationCostIdr: $result->totalDegradationCostIdr,
            netArbitrageProfitIdr: $result->netArbitrageProfitIdr,
            hourlySchedule: $result->hourlySchedule,
            certificateHash: 'corrupted-arbitrage-hash',
            canonicalPayload: $result->canonicalPayload
        );

        $this->expectException(RuntimeException::class);
        $verifier->verify($tampered);
    }
}
