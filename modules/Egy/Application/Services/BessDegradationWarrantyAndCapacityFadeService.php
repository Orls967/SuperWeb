<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Domain\Models\BessAsset;

/**
 * Service untuk audit kapasitas baterai BESS, evaluasi degradasi kapasitas (capacity fade),
 * kalkulasi siklus setara (equivalent full cycles / EFC), dan penentuan eligibility
 * klaim garansi performa pabrikan BESS (Warranty Performance Claim).
 *
 * Menerapkan Zero-Float: Semua metrik kapasitas menggunakan milli-kWh (mkWh) atau kWh integer,
 * rasio degradasi menggunakan basis points (bps, 10000 = 100.00%), dan kompensasi dalam IDR integer.
 */
class BessDegradationWarrantyAndCapacityFadeService
{
    /**
     * Ambang batas kapasitas terjamin standar industri (70.00% = 7000 bps).
     */
    public const DEFAULT_WARRANTED_SOH_BPS = 7000;

    /**
     * Batas siklus garansi standar (misal 4000 full cycles).
     */
    public const DEFAULT_WARRANTED_CYCLES = 4000;

    /**
     * Hitung degradasi kapasitas dan status klaim garansi untuk BESS tertentu.
     *
     * @return array{
     *     bess_id: string,
     *     bess_code: string,
     *     nameplate_capacity_kwh: int,
     *     current_capacity_kwh: int,
     *     capacity_loss_kwh: int,
     *     current_soh_bps: int,
     *     warranted_soh_bps: int,
     *     total_cycle_count: int,
     *     warranted_cycles: int,
     *     warranty_status: string,
     *     is_eligible_for_claim: string,
     *     claimable_capacity_deficit_kwh: int,
     *     estimated_compensation_idr: int,
     *     warranty_remedy_recommendation: string
     * }
     */
    public function evaluateWarrantyClaim(
        BessAsset $bess,
        int $costPerKwhReplacementIdr = 2500000,
        int $warrantedSohBps = self::DEFAULT_WARRANTED_SOH_BPS,
        int $warrantedCycles = self::DEFAULT_WARRANTED_CYCLES
    ): array {
        $nameplateKwh = (int) $bess->capacity_kwh;
        $currentKwh = (int) $bess->current_capacity_kwh;
        $cycleCount = (int) $bess->cycle_count;

        if ($nameplateKwh <= 0) {
            $nameplateKwh = 1;
        }

        // Hitung SoH aktual dalam basis points
        $actualSohBps = (int) intdiv($currentKwh * 10000, $nameplateKwh);
        $capacityLossKwh = max(0, $nameplateKwh - $currentKwh);

        // Batas kapasitas yang dijamin oleh kontrak
        $guaranteedMinKwh = (int) intdiv($nameplateKwh * $warrantedSohBps, 10000);

        // Cek eligibility: jika siklus belum melampaui warrantedCycles dan SoH di bawah batas garansi
        $withinWarrantyCycleLimit = $cycleCount <= $warrantedCycles;
        $belowGuaranteedThreshold = $currentKwh < $guaranteedMinKwh;

        $isEligible = ($withinWarrantyCycleLimit && $belowGuaranteedThreshold) ? 'ELIGIBLE' : 'NOT_ELIGIBLE';

        $deficitKwh = 0;
        $compensationIdr = 0;
        $remedy = 'NO_ACTION_REQUIRED';

        if ($isEligible === 'ELIGIBLE') {
            $deficitKwh = max(0, $guaranteedMinKwh - $currentKwh);
            $compensationIdr = $deficitKwh * $costPerKwhReplacementIdr;

            if ($actualSohBps < 5000) {
                $remedy = 'FULL_MODULE_REPLACEMENT';
            } elseif ($actualSohBps < 6500) {
                $remedy = 'CELL_AUGMENTATION_AND_REBALANCING';
            } else {
                $remedy = 'FINANCIAL_LIQUIDATED_DAMAGES';
            }
        } elseif (! $withinWarrantyCycleLimit && $belowGuaranteedThreshold) {
            $remedy = 'END_OF_LIFE_REPURPOSE_OR_RECYCLE';
        }

        $warrantyStatus = 'ACTIVE';
        if ($cycleCount > $warrantedCycles) {
            $warrantyStatus = 'EXPIRED_BY_USAGE';
        }

        return [
            'bess_id' => (string) $bess->id,
            'bess_code' => (string) $bess->bess_code,
            'nameplate_capacity_kwh' => $nameplateKwh,
            'current_capacity_kwh' => $currentKwh,
            'capacity_loss_kwh' => $capacityLossKwh,
            'current_soh_bps' => $actualSohBps,
            'warranted_soh_bps' => $warrantedSohBps,
            'total_cycle_count' => $cycleCount,
            'warranted_cycles' => $warrantedCycles,
            'warranty_status' => $warrantyStatus,
            'is_eligible_for_claim' => $isEligible,
            'claimable_capacity_deficit_kwh' => $deficitKwh,
            'estimated_compensation_idr' => $compensationIdr,
            'warranty_remedy_recommendation' => $remedy,
        ];
    }

    /**
     * Hitung laju degradasi kapasitas per 1000 siklus (capacity fade rate in bps per 1k cycles).
     */
    public function calculateFadeRatePerThousandCycles(BessAsset $bess): int
    {
        $nameplateKwh = (int) $bess->capacity_kwh;
        $currentKwh = (int) $bess->current_capacity_kwh;
        $cycles = (int) $bess->cycle_count;

        if ($cycles <= 0 || $nameplateKwh <= 0) {
            return 0;
        }

        $lossKwh = max(0, $nameplateKwh - $currentKwh);
        $totalLossBps = (int) intdiv($lossKwh * 10000, $nameplateKwh);

        // Laju fade per 1.000 siklus
        return (int) intdiv($totalLossBps * 1000, $cycles);
    }

    /**
     * Proyeksikan sisa umur pakai BESS (remaining useful life dalam siklus) hingga mencapai batas SoH tertentu.
     */
    public function projectRemainingUsefulLifeCycles(BessAsset $bess, int $endOfLifeSohBps = 6000): int
    {
        $nameplateKwh = (int) $bess->capacity_kwh;
        $currentKwh = (int) $bess->current_capacity_kwh;
        $cycles = (int) $bess->cycle_count;

        if ($cycles <= 0 || $nameplateKwh <= 0) {
            return self::DEFAULT_WARRANTED_CYCLES;
        }

        $currentSohBps = (int) intdiv($currentKwh * 10000, $nameplateKwh);
        if ($currentSohBps <= $endOfLifeSohBps) {
            return 0;
        }

        $fadeRatePerThousand = $this->calculateFadeRatePerThousandCycles($bess);
        if ($fadeRatePerThousand <= 0) {
            return self::DEFAULT_WARRANTED_CYCLES - $cycles;
        }

        $remainingBpsToFade = $currentSohBps - $endOfLifeSohBps;

        return (int) intdiv($remainingBpsToFade * 1000, $fadeRatePerThousand);
    }
}
