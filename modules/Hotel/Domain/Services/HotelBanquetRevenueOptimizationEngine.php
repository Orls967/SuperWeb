<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetRevenueOptimizationEngine
{
    /**
     * @var array<string, array{min_spend_idr: int, max_pax: int, hourly_overtime_rate_idr: int}>
     */
    private const FUNCTION_ROOMS = [
        'GRAND_BALLROOM' => [
            'min_spend_idr' => 150000000,
            'max_pax' => 600,
            'hourly_overtime_rate_idr' => 15000000,
        ],
        'JUNIOR_BALLROOM' => [
            'min_spend_idr' => 75000000,
            'max_pax' => 250,
            'hourly_overtime_rate_idr' => 8000000,
        ],
        'OCEAN_VIEW_PAVILION' => [
            'min_spend_idr' => 50000000,
            'max_pax' => 120,
            'hourly_overtime_rate_idr' => 5000000,
        ],
    ];

    /**
     * Menghitung kelayakan komersial penawaran acara banquet, biaya perpanjangan waktu, dan margin bersih.
     *
     * @return array{room_code: string, is_min_spend_met: bool, proposed_spend_idr: int, min_spend_required_idr: int, overtime_charge_idr: int, total_event_price_idr: int}
     */
    public function evaluateCommercialFeasibility(
        string $roomCode,
        int $proposedFoodAndBeverageSpendIdr,
        int $eventDurationHours = 4,
        int $standardIncludedHours = 4
    ): array {
        if (! isset(self::FUNCTION_ROOMS[$roomCode])) {
            throw new InvalidArgumentException("Kode ruang fungsi jamuan tidak dikenal: {$roomCode}");
        }

        $room = self::FUNCTION_ROOMS[$roomCode];
        $isMinSpendMet = $proposedFoodAndBeverageSpendIdr >= $room['min_spend_idr'];

        $overtimeHours = max(0, $eventDurationHours - $standardIncludedHours);
        $overtimeCharge = $overtimeHours * $room['hourly_overtime_rate_idr'];

        $totalEventPrice = max($proposedFoodAndBeverageSpendIdr, $room['min_spend_idr']) + $overtimeCharge;

        return [
            'room_code' => $roomCode,
            'is_min_spend_met' => $isMinSpendMet,
            'proposed_spend_idr' => $proposedFoodAndBeverageSpendIdr,
            'min_spend_required_idr' => $room['min_spend_idr'],
            'overtime_charge_idr' => $overtimeCharge,
            'total_event_price_idr' => $totalEventPrice,
        ];
    }
}
