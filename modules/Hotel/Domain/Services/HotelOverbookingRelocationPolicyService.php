<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Kebijakan Kompensasi Relokasi Tamu Walk Terstandarisasi.
 */
final class HotelOverbookingRelocationPolicyService
{
    /**
     * @return array{upgrade_category_granted: bool, transport_allowance_idr: int, meal_voucher_idr: int, goodwill_points: int}
     */
    public function determineRelocationPackage(string $guestTier, int $roomDailyRateIdr): array
    {
        $transport = 350000;
        $meal = 250000;
        $points = 5000;

        if ($guestTier === 'PLATINUM') {
            $transport = 750000; // Limousine / Alphard
            $meal = 500000;
            $points = 15000;
        } elseif ($guestTier === 'GOLD') {
            $transport = 500000;
            $meal = 350000;
            $points = 10000;
        }

        return [
            'upgrade_category_granted' => true,
            'transport_allowance_idr' => $transport,
            'meal_voucher_idr' => $meal,
            'goodwill_points' => $points,
        ];
    }
}
