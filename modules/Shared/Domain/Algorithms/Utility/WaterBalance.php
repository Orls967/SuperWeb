<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * E5 Utility\WaterBalance
 *
 * Algoritma bersertifikat penghitungan neraca air standar IWA (International Water Association).
 * Menghitung volume masukan sistem (SIV), konsumsi resmi berbayar & tidak berbayar,
 * kehilangan komersial (apparent), kehilangan fisik pipa (real), dan Non-Revenue Water (NRW).
 *
 * Kompleksitas: O(1) waktu dan ruang.
 */
final class WaterBalance
{
    public function __construct(
        private readonly WaterBalanceCertificateVerifier $verifier = new WaterBalanceCertificateVerifier
    ) {}

    /**
     * Hitung neraca air lengkap dengan validasi invarian.
     *
     * @param  int  $systemInputVolumeL  Air baku/produksi masuk ke jaringan (liter)
     * @param  int  $billedAuthorizedL  Air resmi terbayar (liter)
     * @param  int  $unbilledAuthorizedL  Air resmi tanpa bayar, misal pemadam kebakaran (liter)
     * @param  int  $apparentLossesL  Kehilangan komersial/meteran macet (liter)
     * @param  int|null  $realLossesL  Kehilangan fisik/kebocoran pipa (liter). Jika null, dihitung sisa residual.
     */
    public function calculate(
        int $systemInputVolumeL,
        int $billedAuthorizedL,
        int $unbilledAuthorizedL = 0,
        int $apparentLossesL = 0,
        ?int $realLossesL = null
    ): WaterBalanceResult {
        if ($systemInputVolumeL < 0) {
            throw new InvalidArgumentException("System Input Volume tidak boleh negatif: {$systemInputVolumeL}");
        }
        if ($billedAuthorizedL < 0) {
            throw new InvalidArgumentException("Billed Authorized tidak boleh negatif: {$billedAuthorizedL}");
        }
        if ($unbilledAuthorizedL < 0) {
            throw new InvalidArgumentException("Unbilled Authorized tidak boleh negatif: {$unbilledAuthorizedL}");
        }
        if ($apparentLossesL < 0) {
            throw new InvalidArgumentException("Apparent Losses tidak boleh negatif: {$apparentLossesL}");
        }

        $knownConsumed = $billedAuthorizedL + $unbilledAuthorizedL + $apparentLossesL;
        if ($knownConsumed > $systemInputVolumeL) {
            throw new InvalidArgumentException("Total komponen terhitung ({$knownConsumed}) melebihi SIV ({$systemInputVolumeL})");
        }

        if ($realLossesL === null) {
            $computedRealLosses = $systemInputVolumeL - $knownConsumed;
        } else {
            if ($realLossesL < 0) {
                throw new InvalidArgumentException("Real Losses tidak boleh negatif: {$realLossesL}");
            }
            if ($knownConsumed + $realLossesL !== $systemInputVolumeL) {
                throw new InvalidArgumentException(
                    'Jumlah seluruh komponen ('.($knownConsumed + $realLossesL).") harus sama dengan SIV ({$systemInputVolumeL})"
                );
            }
            $computedRealLosses = $realLossesL;
        }

        $totalLosses = $apparentLossesL + $computedRealLosses;
        $nonRevenueWater = $systemInputVolumeL - $billedAuthorizedL;
        $nrwBasisPoints = $systemInputVolumeL > 0
            ? (int) intdiv($nonRevenueWater * 10000, $systemInputVolumeL)
            : 0;

        $result = new WaterBalanceResult(
            systemInputVolumeL: $systemInputVolumeL,
            billedAuthorizedL: $billedAuthorizedL,
            unbilledAuthorizedL: $unbilledAuthorizedL,
            apparentLossesL: $apparentLossesL,
            realLossesL: $computedRealLosses,
            totalLossesL: $totalLosses,
            nonRevenueWaterL: $nonRevenueWater,
            nrwBasisPoints: $nrwBasisPoints,
            certified: true
        );

        $this->verifier->verify($result);

        return $result;
    }
}
