<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;

class HotelGuestLoyaltyExpirationEngine
{
    /**
     * Memproses masa kedaluwarsa poin loyalitas tamu yang tidak memiliki aktivitas menginap dalam 24 bulan (Point Expiry Policy).
     *
     * @return array{guest_id: string, active_points: int, expired_points_deducted: int, remaining_points: int, notification_sent: bool, processed_at: string}
     */
    public function processPointsExpiration(string $guestId, int $currentPointsBalance, int $monthsSinceLastActivity): array
    {
        $expiredDeducted = 0;
        $remaining = $currentPointsBalance;
        $notified = false;

        if ($monthsSinceLastActivity >= 24) {
            // Poin hangus penuh jika pasif 2 tahun
            $expiredDeducted = $currentPointsBalance;
            $remaining = 0;
        } elseif ($monthsSinceLastActivity >= 21) {
            // Kirim notifikasi peringatan dini jika tersisa 3 bulan sebelum hangus
            $notified = true;
        }

        return [
            'guest_id' => $guestId,
            'active_points' => $currentPointsBalance,
            'expired_points_deducted' => $expiredDeducted,
            'remaining_points' => $remaining,
            'notification_sent' => $notified,
            'processed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
