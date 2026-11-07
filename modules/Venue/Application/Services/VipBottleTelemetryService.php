<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\TableBottleOrder;

class VipBottleTelemetryService
{
    /**
     * Memvalidasi data penuangan botol liquor (spout telemetry) terhadap pesanan meja VIP.
     *
     * @return array{
     *     order_id: int,
     *     dispensed_ml: int,
     *     expected_ml: int,
     *     variance_ml: int,
     *     variance_pct: float,
     *     over_pour_flag: bool
     * }
     */
    public function recordSpoutPour(
        TableBottleOrder $order,
        int $actualDispensedMl
    ): array {
        $expectedMl = (int) $order->spout_ml_dispensed;
        $variance = $actualDispensedMl - $expectedMl;
        $variancePct = $expectedMl > 0 ? round(($variance / $expectedMl) * 100, 2) : 0.0;
        $isOverPour = ($variance > 50); // Deviasi lebih dari 50 ml dianggap over-pour

        $order->variance_ml = $variance;
        $order->save();

        return [
            'order_id' => $order->id,
            'dispensed_ml' => $actualDispensedMl,
            'expected_ml' => $expectedMl,
            'variance_ml' => $variance,
            'variance_pct' => $variancePct,
            'over_pour_flag' => $isOverPour,
        ];
    }

    /**
     * Menghitung total nilai botol yang telah disajikan untuk satu sesi booking meja.
     */
    public function calculateTotalBottleConsumption(EntertainmentTableBooking $booking): int
    {
        return (int) TableBottleOrder::where('booking_id', $booking->id)
            ->where('status', 'served')
            ->sum('total_price_idr');
    }
}
