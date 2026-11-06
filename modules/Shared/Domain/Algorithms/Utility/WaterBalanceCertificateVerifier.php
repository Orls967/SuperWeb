<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use RuntimeException;

/**
 * Verifier invarian neraca air standar IWA.
 *
 * Invarian:
 * 1. SIV = BilledAuthorized + UnbilledAuthorized + ApparentLosses + RealLosses
 * 2. TotalLosses = ApparentLosses + RealLosses
 * 3. NRW = UnbilledAuthorized + TotalLosses = SIV - BilledAuthorized
 * 4. 0 <= NRW <= SIV
 * 5. Semua komponen bernilai integer >= 0
 */
final class WaterBalanceCertificateVerifier
{
    public function verify(WaterBalanceResult $result): bool
    {
        if ($result->systemInputVolumeL < 0 ||
            $result->billedAuthorizedL < 0 ||
            $result->unbilledAuthorizedL < 0 ||
            $result->apparentLossesL < 0 ||
            $result->realLossesL < 0) {
            throw new RuntimeException('Komponen neraca air tidak boleh bernilai negatif');
        }

        $computedLosses = $result->apparentLossesL + $result->realLossesL;
        if ($computedLosses !== $result->totalLossesL) {
            throw new RuntimeException("Total kehilangan air ({$result->totalLossesL}) tidak sama dengan penjumlahan losses ({$computedLosses})");
        }

        $computedSiv = $result->billedAuthorizedL + $result->unbilledAuthorizedL + $result->totalLossesL;
        if ($computedSiv !== $result->systemInputVolumeL) {
            throw new RuntimeException("SIV ({$result->systemInputVolumeL}) tidak sama dengan total komponen ({$computedSiv})");
        }

        $computedNrw = $result->systemInputVolumeL - $result->billedAuthorizedL;
        if ($computedNrw !== $result->nonRevenueWaterL) {
            throw new RuntimeException("NRW ({$result->nonRevenueWaterL}) tidak sama dengan SIV - Billed ({$computedNrw})");
        }

        if ($result->systemInputVolumeL > 0) {
            $expectedBps = (int) intdiv($result->nonRevenueWaterL * 10000, $result->systemInputVolumeL);
            if ($expectedBps !== $result->nrwBasisPoints) {
                throw new RuntimeException("NRW bps ({$result->nrwBasisPoints}) tidak konsisten dengan ({$expectedBps})");
            }
        } elseif ($result->nrwBasisPoints !== 0) {
            throw new RuntimeException('NRW bps harus 0 saat SIV 0');
        }

        return true;
    }
}
