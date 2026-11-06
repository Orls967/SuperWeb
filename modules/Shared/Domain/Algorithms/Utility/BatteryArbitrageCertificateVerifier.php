<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifikator sertifikat kriptografis untuk algoritma E12 BESS Arbitrage.
 */
final class BatteryArbitrageCertificateVerifier
{
    public function verify(BatteryArbitrageResult $result): bool
    {
        $expectedPayload = sprintf(
            'BESS-ARBITRAGE|CAP:%d|CHG_KWH:%d|DIS_KWH:%d|CHG_COST:%d|DIS_REV:%d|DEG_EXP:%d|NET:%d|FINAL_SOC:%d',
            $result->capacityKwh,
            $result->totalChargedKwh,
            $result->totalDischargedKwh,
            $result->totalChargingCostIdr,
            $result->totalDischargingRevenueIdr,
            $result->totalDegradationCostIdr,
            $result->netArbitrageProfitIdr,
            $result->finalSocBps
        );

        if ($expectedPayload !== $result->canonicalPayload) {
            throw new RuntimeException('Payload kanonikal hasil arbitrase BESS tidak cocok.');
        }

        $expectedHash = hash('sha256', $expectedPayload);
        if ($expectedHash !== $result->certificateHash) {
            throw new RuntimeException('Hash sertifikat arbitrase BESS tidak valid atau telah dimanipulasi.');
        }

        return true;
    }
}
