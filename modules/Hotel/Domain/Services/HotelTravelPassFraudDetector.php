<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlAllianceTransferLog;
use Modules\Hotel\Domain\Models\HtlTravelPass;

final class HotelTravelPassFraudDetector
{
    public const MAX_TRANSFERS_PER_HOUR = 3;

    public const MAX_POINTS_TRANSFERRED_DAILY = 500000;

    public const HIGH_VELOCITY_TIME_WINDOW_MINUTES = 60;

    /**
     * @return array{
     *     travel_pass_id: string,
     *     risk_score: int,
     *     risk_level: string,
     *     is_flagged: bool,
     *     reasons: list<string>,
     *     action_recommended: string
     * }
     */
    public function evaluateTransferRisk(
        string $travelPassId,
        string $partnerCode,
        int $pointsToTransfer,
        string $clientIp = '127.0.0.1'
    ): array {
        $reasons = [];
        $riskScore = 0;

        $pass = HtlTravelPass::find($travelPassId);
        if (! $pass) {
            return [
                'travel_pass_id' => $travelPassId,
                'risk_score' => 100,
                'risk_level' => 'CRITICAL',
                'is_flagged' => true,
                'reasons' => ['Travel pass tidak ditemukan.'],
                'action_recommended' => 'REJECT_AND_LOG',
            ];
        }

        // Rule 1: Account Age Check
        $accountAgeDays = $pass->created_at->diffInDays(now());
        if ($accountAgeDays < 7 && $pointsToTransfer > 50000) {
            $riskScore += 40;
            $reasons[] = "Akun baru berumur {$accountAgeDays} hari melakukan transfer poin dalam jumlah besar ({$pointsToTransfer} PTS).";
        }

        // Rule 2: High Velocity Transfers in Last Hour
        $oneHourAgo = now()->subMinutes(self::HIGH_VELOCITY_TIME_WINDOW_MINUTES);
        $recentTransfersCount = HtlAllianceTransferLog::where('travel_pass_id', $pass->id)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($recentTransfersCount >= self::MAX_TRANSFERS_PER_HOUR) {
            $riskScore += 35;
            $reasons[] = "Frekuensi transfer abnormal: {$recentTransfersCount} transfer dalam 60 menit terakhir.";
        }

        // Rule 3: Daily Points Outflow Limit
        $twentyFourHoursAgo = now()->subHours(24);
        $totalDailyPointsTransferred = (int) HtlAllianceTransferLog::where('travel_pass_id', $pass->id)
            ->where('created_at', '>=', $twentyFourHoursAgo)
            ->where('status', 'SUCCESS')
            ->sum('points_debited');

        if (($totalDailyPointsTransferred + $pointsToTransfer) > self::MAX_POINTS_TRANSFERRED_DAILY) {
            $riskScore += 50;
            $reasons[] = 'Melebihi batas agregat transfer harian: total '.($totalDailyPointsTransferred + $pointsToTransfer).' PTS.';
        }

        // Rule 4: Sudden Outflow of All Points (Account Drainage Pattern)
        if ($pass->pts_balance > 0 && ($pointsToTransfer / $pass->pts_balance) >= 0.95 && $pass->pts_balance >= 100000) {
            $riskScore += 25;
            $reasons[] = 'Pola pengosongan akun (account drainage): transfer >= 95% dari total saldo poin.';
        }

        $riskLevel = match (true) {
            $riskScore >= 75 => 'CRITICAL',
            $riskScore >= 45 => 'HIGH',
            $riskScore >= 20 => 'MEDIUM',
            default => 'LOW',
        };

        $isFlagged = $riskScore >= 45;
        $action = match ($riskLevel) {
            'CRITICAL' => 'REJECT_AND_FREEZE_ACCOUNT',
            'HIGH' => 'HOLD_FOR_MANUAL_REVIEW',
            'MEDIUM' => 'ALLOW_WITH_2FA_CHALLENGE',
            default => 'ALLOW_AUTOMATICALLY',
        };

        return [
            'travel_pass_id' => $pass->id,
            'risk_score' => min(100, $riskScore),
            'risk_level' => $riskLevel,
            'is_flagged' => $isFlagged,
            'reasons' => $reasons,
            'action_recommended' => $action,
        ];
    }
}
