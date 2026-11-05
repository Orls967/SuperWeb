<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Insurance\Domain\Models\WellnessLog;
use Modules\Insurance\Domain\Models\WellnessReward;

final class RecordWellnessActivityAction
{
    /**
     * Mencatat telemetri aktivitas harian pengguna dan mengevaluasi reward dengan guard anti-gaming.
     *
     * @return array{
     *     log: WellnessLog,
     *     reward: ?WellnessReward,
     *     anti_gaming_flagged: bool
     * }
     */
    public function execute(
        string $userPartyId,
        string $logDate,
        int $dailySteps,
        int $avgHeartRateBpm,
        int $sleepMinutes,
        string $deviceHash,
    ): array {
        // Aturan Anti-Gaming (Pencegahan manipulasi pedometer / emulator)
        $isFlagged = false;
        if ($dailySteps > 50000) {
            $isFlagged = true; // Bot/emulator yang menyuntikkan jutaan langkah
        }
        if ($avgHeartRateBpm < 40 || $avgHeartRateBpm > 220) {
            $isFlagged = true; // Anomali sinyal biosensor
        }

        // Skor kesehatan berbasis aktivitas harian (0 - 100)
        $score = 50;
        if (! $isFlagged) {
            $stepScore = min(40, (int) intdiv($dailySteps, 250)); // 10.000 langkah = 40 poin
            $sleepScore = ($sleepMinutes >= 420 && $sleepMinutes <= 540) ? 30 : 15; // 7-9 jam tidur = 30 poin
            $hrScore = ($avgHeartRateBpm >= 60 && $avgHeartRateBpm <= 85) ? 30 : 15;
            $score = min(100, $stepScore + $sleepScore + $hrScore);
        }

        return DB::transaction(function () use ($userPartyId, $logDate, $dailySteps, $avgHeartRateBpm, $sleepMinutes, $score, $isFlagged, $deviceHash) {
            $log = WellnessLog::updateOrCreate(
                ['user_party_id' => $userPartyId, 'log_date' => $logDate],
                [
                    'daily_steps' => $dailySteps,
                    'avg_heart_rate_bpm' => $avgHeartRateBpm,
                    'sleep_minutes' => $sleepMinutes,
                    'health_score' => $score,
                    'is_flagged_gaming' => $isFlagged,
                    'source_device_hash' => $deviceHash,
                ]
            );

            $reward = null;
            // Jika skor konsisten tinggi dan bebas fraud, terbitkan reward diskon premi
            if ($score >= 80 && ! $isFlagged) {
                $monthPeriod = CarbonImmutable::parse($logDate)->startOfMonth()->toDateString();
                $reward = WellnessReward::updateOrCreate(
                    ['user_party_id' => $userPartyId, 'period_month' => $monthPeriod],
                    [
                        'reward_type' => 'PREMIUM_DISCOUNT_BPS',
                        'reward_value' => 500, // 5% diskon premi
                        'badge_title' => 'Gold Healthy Habit Champion',
                        'is_applied' => true,
                    ]
                );
            }

            return [
                'log' => $log,
                'reward' => $reward,
                'anti_gaming_flagged' => $isFlagged,
            ];
        });
    }
}
