<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetStaffingAllocationMatrix
{
    /**
     * Menghitung matriks kebutuhan staf operasional perjamuan (kapten, pramusaji, bartender, runner, stewarding).
     *
     * @return array{pax_count: int, service_style: string, captain_count: int, waiter_count: int, bartender_count: int, steward_count: int, total_headcount: int, labor_budget_idr: int}
     */
    public function computeStaffingRatios(
        int $paxCount,
        string $serviceStyle = 'PLATED_FINE_DINING', // PLATED_FINE_DINING, BUFFET_STANDARD, COCKTAIL_RECEPTION
        int $hourlyLaborRateIdr = 45000,
        int $shiftDurationHours = 6
    ): array {
        if ($paxCount <= 0) {
            throw new InvalidArgumentException('Pax count harus lebih besar dari nol.');
        }

        $waiterRatio = match ($serviceStyle) {
            'PLATED_FINE_DINING' => 10,
            'COCKTAIL_RECEPTION' => 35,
            default => 25,
        };

        $waiters = (int) ceil($paxCount / $waiterRatio);
        $captains = (int) max(1, ceil($paxCount / 150));
        $bartenders = (int) max(1, ceil($paxCount / 100));
        $stewards = (int) max(2, ceil($paxCount / 75));

        $totalHeadcount = $waiters + $captains + $bartenders + $stewards;
        $laborBudget = $totalHeadcount * $shiftDurationHours * $hourlyLaborRateIdr;

        return [
            'pax_count' => $paxCount,
            'service_style' => $serviceStyle,
            'captain_count' => $captains,
            'waiter_count' => $waiters,
            'bartender_count' => $bartenders,
            'steward_count' => $stewards,
            'total_headcount' => $totalHeadcount,
            'labor_budget_idr' => $laborBudget,
        ];
    }
}
