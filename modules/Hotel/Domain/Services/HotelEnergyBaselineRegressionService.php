<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelRoomEnergyLog;

/**
 * Layanan Estimasi Emisi Karbon Terhindar & Efisiensi Pendinginan Kamar.
 */
final class HotelEnergyBaselineRegressionService
{
    /**
     * @return array{total_kwh_saved: float, co2_avoided_kg: float, cost_avoided_idr: int}
     */
    public function calculateAvoidedCarbon(string|int $roomId, int $days = 30): array
    {
        $logs = HotelRoomEnergyLog::where('room_id', (string) $roomId)
            ->where('hvac_mode', 'setback')
            ->latest()
            ->take($days * 24)
            ->get();

        $totalWh = (int) $logs->sum('energy_saved_wh');
        $totalKwh = $totalWh / 1000.0;

        // Faktor emisi jaringan listrik Jawa-Bali: ~0.85 kg CO2 per kWh
        $co2Kg = round($totalKwh * 0.85, 2);
        // Tarif listrik per kWh bisnis hotel: ~Rp 1.450
        $costAvoided = (int) round($totalKwh * 1450);

        return [
            'total_kwh_saved' => $totalKwh,
            'co2_avoided_kg' => $co2Kg,
            'cost_avoided_idr' => $costAvoided,
        ];
    }
}
