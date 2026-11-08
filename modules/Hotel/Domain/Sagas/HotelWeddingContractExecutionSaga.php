<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Sagas;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Models\HtlWeddingMilestoneSchedule;

final class HotelWeddingContractExecutionSaga
{
    /**
     * @return array<string, mixed>
     */
    public function advanceMilestone(string $scheduleId): array
    {
        $schedule = HtlWeddingMilestoneSchedule::findOrFail($scheduleId);

        DB::beginTransaction();
        try {
            $paid = (float) $schedule->paid_amount_idr;
            $total = (float) $schedule->amount_idr;
            $ratio = $total > 0 ? ($paid / $total) : 0.0;

            $unlockedPerks = [];

            // Perk 1: If >= 20% deposit paid
            if ($ratio >= 0.20) {
                $unlockedPerks[] = 'Complimentary Honeymoon Presidential Suite with Champagne Breakfast';
            }

            // Perk 2: If >= 50% paid
            if ($ratio >= 0.50) {
                $unlockedPerks[] = 'Chef Table Menu Tasting Session for 6 VIPs';
            }

            // Perk 3: If >= 80% paid
            if ($ratio >= 0.80) {
                $unlockedPerks[] = 'Luxury Airport Limousine Transfer for Bridal Couple';
            }

            // Perk 4: If 100% paid
            if ($ratio >= 1.00) {
                $unlockedPerks[] = 'Complimentary 4-Hour Ballroom Rehearsal with Sound & Lighting Engineer';
                $schedule->update(['status' => 'PAID']);
            }

            DB::commit();

            Log::info('HotelWeddingContractExecutionSaga milestone advanced', [
                'schedule_id' => $schedule->id,
                'paid_ratio' => round($ratio * 100, 2).'%',
                'unlocked_perks' => $unlockedPerks,
            ]);

            return [
                'status' => 'processed',
                'schedule_id' => $schedule->id,
                'paid_ratio' => round($ratio * 100, 2),
                'unlocked_perks' => $unlockedPerks,
                'remaining_balance' => max(0.0, $total - $paid),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to advance wedding milestone {$scheduleId}: ".$e->getMessage());
            throw $e;
        }
    }
}
