<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelSpaResourceCapacityPlanner
{
    /**
     * Memperkirakan kapasitas utilisasi ruang perawatan dan terapis spa pada tanggal tertentu.
     *
     * @return array{target_date: string, total_rooms_available: int, total_slots_day: int, booked_slots: int, available_slots: int, utilization_ratio_pct: float, recommendation: string}
     */
    public function planDayCapacity(
        string $targetDate,
        int $activeChambers = 8,
        int $therapistsOnDuty = 6,
        int $confirmedBookings = 18
    ): array {
        // Asumsi jam buka 09:00 - 21:00 = 12 jam. Rata-rata 1 sesi + turnover = 1.5 jam -> 8 slot per terapis
        $slotsPerTherapist = 8;
        $totalCapacitySlots = min($activeChambers * 8, $therapistsOnDuty * $slotsPerTherapist);

        $availableSlots = max(0, $totalCapacitySlots - $confirmedBookings);
        $utilizationPct = $totalCapacitySlots > 0 ? round(($confirmedBookings / $totalCapacitySlots) * 100, 2) : 0;

        $rec = 'NORMAL_OPERATIONS';
        if ($utilizationPct >= 90.0) {
            $rec = 'FULL_CAPACITY_ENABLE_PEAK_SURGE';
        } elseif ($utilizationPct <= 40.0) {
            $rec = 'LOW_DEMAND_OFFER_IN_VILLA_MASSAGE_PROMO';
        }

        return [
            'target_date' => $targetDate,
            'total_rooms_available' => $activeChambers,
            'total_slots_day' => $totalCapacitySlots,
            'booked_slots' => $confirmedBookings,
            'available_slots' => $availableSlots,
            'utilization_ratio_pct' => $utilizationPct,
            'recommendation' => $rec,
        ];
    }
}
