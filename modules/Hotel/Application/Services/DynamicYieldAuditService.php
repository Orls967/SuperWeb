<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Layanan audit yield management dan optimasi revenue hotel deterministik.
 */
final class DynamicYieldAuditService
{
    public const TOTAL_ROOMS = 80;

    /**
     * @return array{
     *     total_inventory: int,
     *     occupied_rooms: int,
     *     current_occupancy_pct: float,
     *     recommended_rate_multiplier: float,
     *     suggested_strategy: string
     * }
     */
    public function calculateYieldRecommendation(): array
    {
        $occupied = HotelReservation::where('status', 'checked_in')->count();
        $occupancyPct = round(($occupied / self::TOTAL_ROOMS) * 100, 2);

        $multiplier = match (true) {
            $occupancyPct >= 85.0 => 1.30, // Lonjakan harga saat kamar menipis
            $occupancyPct >= 70.0 => 1.15,
            $occupancyPct >= 40.0 => 1.00,
            default => 0.85,               // Diskon saat sepi
        };

        $strategy = match (true) {
            $occupancyPct >= 85.0 => 'PREMIUM_PRICING',
            $occupancyPct >= 70.0 => 'OPTIMIZED_YIELD',
            $occupancyPct >= 40.0 => 'STANDARD_PRICING',
            default => 'DISCOUNT_PROMOTION',
        };

        return [
            'total_inventory' => self::TOTAL_ROOMS,
            'occupied_rooms' => $occupied,
            'current_occupancy_pct' => $occupancyPct,
            'recommended_rate_multiplier' => $multiplier,
            'suggested_strategy' => $strategy,
        ];
    }
}
