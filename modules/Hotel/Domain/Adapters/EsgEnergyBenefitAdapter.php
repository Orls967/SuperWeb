<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;

/**
 * Adaptor Integrasi ESG Emission Benefit Recorder & Egy G7-K22 Meter Interval
 */
final class EsgEnergyBenefitAdapter
{
    /**
     * @return array{esg_record_id: string, energy_saved_wh: int, carbon_offset_kg: float}
     */
    public static function recordRoomSetbackBenefit(int $propertyId, int $roomId, int $energySavedWh): array
    {
        // 1 kWh = ~0.78 kg CO2 (grid faktor emisi rata-rata Jamali)
        $carbonOffsetKg = round(($energySavedWh / 1000.0) * 0.78, 3);

        return [
            'esg_record_id' => "ESG-HTL-{$propertyId}-{$roomId}-" . time(),
            'energy_saved_wh' => $energySavedWh,
            'carbon_offset_kg' => $carbonOffsetKg,
        ];
    }
}
