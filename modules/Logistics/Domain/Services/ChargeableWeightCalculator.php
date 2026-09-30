<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Logistics\Domain\DTOs\ChargeableWeightResult;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\TransportMode;

class ChargeableWeightCalculator
{
    public const ROAD_VOLUMETRIC_DIVISOR = 6000; // cm³/kg

    public const AIR_VOLUMETRIC_DIVISOR = 6000;  // cm³/kg (IATA)

    public const SEA_CBM_TO_KG = 1000;           // 1 CBM = 1000 kg (W/M)

    public const ROAD_MIN_CHARGEABLE_KG = '1.0';

    public const AIR_MIN_CHARGEABLE_KG = '5.0';

    public const SEA_LCL_MIN_RT = '1.0';

    /**
     * Calculate chargeable weight for one or more packages based on transport mode and service level.
     *
     * @param  array<int, array{weight_g: int, length_mm: int, width_mm: int, height_mm: int}>  $packages
     */
    public function calculate(
        array $packages,
        TransportMode $mode,
        ServiceLevel $serviceLevel
    ): ChargeableWeightResult {
        if ($serviceLevel->isUnitBased()) {
            return new ChargeableWeightResult(
                actualWeightKg: BigDecimal::zero(),
                volumetricWeightKg: BigDecimal::zero(),
                chargeableWeightKg: BigDecimal::one(),
                chargeableWeightGrams: 1000,
                volumeCm3: BigDecimal::zero(),
                volumeCbm: BigDecimal::zero(),
                revenueTons: null,
                isUnitBased: true
            );
        }

        $totalActualWeightGrams = 0;
        $totalVolumeCm3 = BigDecimal::zero();
        $totalVolumeCbm = BigDecimal::zero();

        foreach ($packages as $pkg) {
            $weightG = $pkg['weight_g'] ?? 0;
            $lengthMm = $pkg['length_mm'] ?? 0;
            $widthMm = $pkg['width_mm'] ?? 0;
            $heightMm = $pkg['height_mm'] ?? 0;

            $totalActualWeightGrams += $weightG;

            // Dimensions in cm (mm / 10)
            $lenCm = BigDecimal::of($lengthMm)->dividedBy(BigDecimal::of(10), 4, RoundingMode::HalfUp);
            $widthCm = BigDecimal::of($widthMm)->dividedBy(BigDecimal::of(10), 4, RoundingMode::HalfUp);
            $heightCm = BigDecimal::of($heightMm)->dividedBy(BigDecimal::of(10), 4, RoundingMode::HalfUp);
            $pkgVolCm3 = $lenCm->multipliedBy($widthCm)->multipliedBy($heightCm);
            $totalVolumeCm3 = $totalVolumeCm3->plus($pkgVolCm3);

            // Dimensions in CBM (cm³ / 1,000,000)
            $pkgVolCbm = $pkgVolCm3->dividedBy(BigDecimal::of(1_000_000), 6, RoundingMode::HalfUp);
            $totalVolumeCbm = $totalVolumeCbm->plus($pkgVolCbm);
        }

        $totalActualWeightKg = BigDecimal::of($totalActualWeightGrams)->dividedBy(BigDecimal::of(1000), 4, RoundingMode::HalfUp);

        // Branch by Mode & Service Level
        if ($serviceLevel === ServiceLevel::AirFreight || $mode === TransportMode::AIR) {
            return $this->calculateAir(
                actualKg: $totalActualWeightKg,
                volumeCm3: $totalVolumeCm3,
                volumeCbm: $totalVolumeCbm
            );
        }

        if ($serviceLevel === ServiceLevel::LCL || ($mode === TransportMode::SEA && $serviceLevel !== ServiceLevel::FCL)) {
            return $this->calculateSeaLcl(
                actualKg: $totalActualWeightKg,
                volumeCm3: $totalVolumeCm3,
                volumeCbm: $totalVolumeCbm
            );
        }

        // Default: Road / Courier
        return $this->calculateRoad(
            actualKg: $totalActualWeightKg,
            volumeCm3: $totalVolumeCm3,
            volumeCbm: $totalVolumeCbm
        );
    }

    /**
     * Road & Courier calculation:
     * Divisor: 6000 cm³/kg
     * Rounding: CEIL to next full 1 kg, minimum 1 kg.
     */
    private function calculateRoad(
        BigDecimal $actualKg,
        BigDecimal $volumeCm3,
        BigDecimal $volumeCbm
    ): ChargeableWeightResult {
        $volumetricKg = $volumeCm3->dividedBy(BigDecimal::of(self::ROAD_VOLUMETRIC_DIVISOR), 4, RoundingMode::HalfUp);
        $rawKg = $actualKg->compareTo($volumetricKg) >= 0 ? $actualKg : $volumetricKg;

        // Round UP to next full integer (scale 0)
        $chargeableKg = $rawKg->toScale(0, RoundingMode::Up);

        $minKg = BigDecimal::of(self::ROAD_MIN_CHARGEABLE_KG);
        if ($chargeableKg->compareTo($minKg) < 0) {
            $chargeableKg = $minKg;
        }

        $grams = (int) $chargeableKg->multipliedBy(BigDecimal::of(1000))->toInt();

        return new ChargeableWeightResult(
            actualWeightKg: $actualKg,
            volumetricWeightKg: $volumetricKg,
            chargeableWeightKg: $chargeableKg,
            chargeableWeightGrams: $grams,
            volumeCm3: $volumeCm3,
            volumeCbm: $volumeCbm
        );
    }

    /**
     * Air Freight calculation (IATA):
     * Divisor: 6000 cm³/kg
     * Rounding: CEIL to next 0.5 kg, minimum 5.0 kg.
     */
    private function calculateAir(
        BigDecimal $actualKg,
        BigDecimal $volumeCm3,
        BigDecimal $volumeCbm
    ): ChargeableWeightResult {
        $volumetricKg = $volumeCm3->dividedBy(BigDecimal::of(self::AIR_VOLUMETRIC_DIVISOR), 4, RoundingMode::HalfUp);
        $rawKg = $actualKg->compareTo($volumetricKg) >= 0 ? $actualKg : $volumetricKg;

        // Round UP to nearest 0.5 kg: (raw * 2) rounded UP to scale 0, then divided by 2
        $doubled = $rawKg->multipliedBy(BigDecimal::of(2));
        $ceiledDoubled = $doubled->toScale(0, RoundingMode::Up);
        $chargeableKg = $ceiledDoubled->dividedBy(BigDecimal::of(2), 1, RoundingMode::Unnecessary);

        $minKg = BigDecimal::of(self::AIR_MIN_CHARGEABLE_KG);
        if ($chargeableKg->compareTo($minKg) < 0) {
            $chargeableKg = $minKg;
        }

        $grams = (int) $chargeableKg->multipliedBy(BigDecimal::of(1000))->toInt();

        return new ChargeableWeightResult(
            actualWeightKg: $actualKg,
            volumetricWeightKg: $volumetricKg,
            chargeableWeightKg: $chargeableKg,
            chargeableWeightGrams: $grams,
            volumeCm3: $volumeCm3,
            volumeCbm: $volumeCbm
        );
    }

    /**
     * Sea LCL calculation (W/M):
     * 1 CBM = 1000 kg (Revenue Ton)
     * RT = max(actual weight tonnes, CBM)
     * Minimum: 1 RT.
     */
    private function calculateSeaLcl(
        BigDecimal $actualKg,
        BigDecimal $volumeCm3,
        BigDecimal $volumeCbm
    ): ChargeableWeightResult {
        // Actual tonnes
        $actualTonnes = $actualKg->dividedBy(BigDecimal::of(1000), 4, RoundingMode::HalfUp);
        $volumetricKg = $volumeCbm->multipliedBy(BigDecimal::of(self::SEA_CBM_TO_KG));

        $rawRt = $actualTonnes->compareTo($volumeCbm) >= 0 ? $actualTonnes : $volumeCbm;

        $minRt = BigDecimal::of(self::SEA_LCL_MIN_RT);
        $chargeableRt = $rawRt->compareTo($minRt) < 0 ? $minRt : $rawRt;

        // Chargeable weight equivalent in kg: RT * 1000
        $chargeableKg = $chargeableRt->multipliedBy(BigDecimal::of(self::SEA_CBM_TO_KG))->toScale(2, RoundingMode::HalfUp);
        $grams = (int) $chargeableKg->multipliedBy(BigDecimal::of(1000))->toInt();

        return new ChargeableWeightResult(
            actualWeightKg: $actualKg,
            volumetricWeightKg: $volumetricKg,
            chargeableWeightKg: $chargeableKg,
            chargeableWeightGrams: $grams,
            volumeCm3: $volumeCm3,
            volumeCbm: $volumeCbm,
            revenueTons: $chargeableRt
        );
    }
}
