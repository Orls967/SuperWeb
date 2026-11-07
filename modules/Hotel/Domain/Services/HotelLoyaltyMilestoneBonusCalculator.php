<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelLoyaltyMilestoneBonusCalculator
{
    /**
     * Menghitung bonus akselerator poin loyalitas saat tamu mencapai ambang malam menginap milestone (10, 25, 50 malam).
     *
     * @return array{guest_id: string, current_nights: int, bonus_points_awarded: int, next_milestone_nights: int, voucher_benefit: ?string}
     */
    public function evaluateMilestoneBonus(string $guestId, int $currentEligibleNights): array
    {
        $bonusPoints = 0;
        $voucher = null;
        $nextMilestone = 10;

        if ($currentEligibleNights >= 50) {
            $bonusPoints = 25000;
            $voucher = 'VOUCHER_FREE_WEEKEND_PRESIDENTIAL_VILLA';
            $nextMilestone = 75;
        } elseif ($currentEligibleNights >= 25) {
            $bonusPoints = 10000;
            $voucher = 'VOUCHER_COMPLIMENTARY_FINE_DINING_FOR_TWO';
            $nextMilestone = 50;
        } elseif ($currentEligibleNights >= 10) {
            $bonusPoints = 3000;
            $voucher = 'VOUCHER_SPA_AROMATHERAPY_SESSION';
            $nextMilestone = 25;
        }

        return [
            'guest_id' => $guestId,
            'current_nights' => $currentEligibleNights,
            'bonus_points_awarded' => $bonusPoints,
            'next_milestone_nights' => $nextMilestone,
            'voucher_benefit' => $voucher,
        ];
    }
}
