<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

class HotelLoyaltyTierPreservationAuditor
{
    /**
     * Mengevaluasi masa tenggang perlindungan tier loyalitas (Grace Period Protection) sebelum status tamu diturunkan.
     *
     * @return array{guest_id: string, current_tier: string, nights_accrued: int, shortfall_nights: int, is_grace_granted: bool, grace_expiry_date: ?string, action: string}
     */
    public function auditTierMaintenance(
        string $guestId,
        string $currentTier,
        int $nightsAccruedThisYear,
        int $yearsAsMember = 3
    ): array {
        $requiredNights = match ($currentTier) {
            'PLATINUM' => 25,
            'GOLD' => 10,
            default => 0,
        };

        $shortfall = max(0, $requiredNights - $nightsAccruedThisYear);

        if ($shortfall === 0) {
            return [
                'guest_id' => $guestId,
                'current_tier' => $currentTier,
                'nights_accrued' => $nightsAccruedThisYear,
                'shortfall_nights' => 0,
                'is_grace_granted' => false,
                'grace_expiry_date' => null,
                'action' => 'TIER_QUALIFIED_RENEWED',
            ];
        }

        // Jika anggota loyal >= 2 tahun dan kekurangan <= 3 malam, berikan grace period 3 bulan
        $canGrantGrace = ($yearsAsMember >= 2) && ($shortfall <= 3);
        $expiry = $canGrantGrace ? CarbonImmutable::now()->addMonths(3)->toDateString() : null;
        $action = $canGrantGrace ? 'GRACE_PERIOD_EXTENDED_90_DAYS' : 'TIER_DOWNGRADED';

        return [
            'guest_id' => $guestId,
            'current_tier' => $currentTier,
            'nights_accrued' => $nightsAccruedThisYear,
            'shortfall_nights' => $shortfall,
            'is_grace_granted' => $canGrantGrace,
            'grace_expiry_date' => $expiry,
            'action' => $action,
        ];
    }
}
