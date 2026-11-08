<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;

final class HotelPointsExpirationReminderJob
{
    /**
     * @return array{
     *     batches_expiring_60d: int,
     *     batches_expiring_30d: int,
     *     batches_expiring_7d: int,
     *     total_members_notified: int,
     *     notifications: list<array{
     *         travel_pass_id: string,
     *         member_name: string,
     *         expiring_points: int,
     *         expiration_date: string,
     *         urgency: string,
     *         recommended_action: string
     *     }>,
     *     processed_at: string
     * }
     */
    public function scanAndGenerateExpiringPointsReminders(): array
    {
        $now = now();
        $in60d = $now->copy()->addDays(60);
        $in30d = $now->copy()->addDays(30);
        $in7d = $now->copy()->addDays(7);

        $batches = HtlTravelPassPointBatch::whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
            ->where('expires_at', '<=', $in60d)
            ->where('expires_at', '>', $now)
            ->with('travelPass')
            ->get();

        $notifications = [];
        $notifiedPasses = [];
        $count60 = 0;
        $count30 = 0;
        $count7 = 0;

        foreach ($batches as $batch) {
            $pass = $batch->travelPass;
            if (! $pass) {
                continue;
            }

            $expiresAt = $batch->expires_at;
            $daysLeft = $now->diffInDays($expiresAt);

            $urgency = match (true) {
                $daysLeft <= 7 => 'CRITICAL_7_DAYS',
                $daysLeft <= 30 => 'HIGH_30_DAYS',
                default => 'MEDIUM_60_DAYS',
            };

            if ($daysLeft <= 7) {
                $count7++;
            } elseif ($daysLeft <= 30) {
                $count30++;
            } else {
                $count60++;
            }

            $notifiedPasses[$pass->id] = true;

            $notifications[] = [
                'travel_pass_id' => $pass->id,
                'member_name' => "Member {$pass->pass_number}",
                'expiring_points' => (int) $batch->remaining_pts,
                'expiration_date' => $expiresAt->toDateString(),
                'urgency' => $urgency,
                'recommended_action' => $daysLeft <= 7
                    ? 'Tukarkan segera untuk menginap akhir pekan ini atau donasi kredit karbon ESG!'
                    : 'Gunakan poin Anda untuk voucher belanja mall & dining sebelum hangus.',
            ];
        }

        return [
            'batches_expiring_60d' => $count60,
            'batches_expiring_30d' => $count30,
            'batches_expiring_7d' => $count7,
            'total_members_notified' => count($notifiedPasses),
            'notifications' => $notifications,
            'processed_at' => now()->toIso8601String(),
        ];
    }
}
