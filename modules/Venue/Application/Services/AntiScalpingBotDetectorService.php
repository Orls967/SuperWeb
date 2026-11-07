<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Core\Contracts\IdentityVelocityRiskScorer;
use Modules\Venue\Application\Actions\BlacklistScalperAccountAction;
use Modules\Venue\Domain\DTOs\ScalperFraudRiskProfileDTO;
use Modules\Venue\Domain\Models\ScalperBlacklistLog;

final class AntiScalpingBotDetectorService
{
    public function __construct(
        private readonly BlacklistScalperAccountAction $blacklistAction,
        private readonly ?IdentityVelocityRiskScorer $velocityScorer = null
    ) {}

    /**
     * Evaluasi multi-dimensi indikator bot scalper.
     */
    public function evaluateUserRisk(
        int $userId,
        string $ipAddress,
        ?string $deviceFingerprint,
        int $requestsInLastMinute,
        int $secondaryTicketsListedIn24h
    ): ScalperFraudRiskProfileDTO {
        $riskFlags = [];

        // 1. Skor Kecepatan Akses (Velocity RPM)
        $velocityScore = 0;
        if ($requestsInLastMinute > 60) {
            $velocityScore = 100;
            $riskFlags[] = 'VELOCITY_FLOOD_HIGH';
        } elseif ($requestsInLastMinute > 30) {
            $velocityScore = 60;
            $riskFlags[] = 'VELOCITY_ELEVATED';
        }

        // 2. Anomali Perangkat (Device Fingerprint)
        $deviceScore = 0;
        if ($deviceFingerprint === null || trim($deviceFingerprint) === '' || strlen($deviceFingerprint) < 16) {
            $deviceScore = 50;
            $riskFlags[] = 'DEVICE_FINGERPRINT_MISSING_OR_SUSPICIOUS';
        }

        // 3. Reputasi Jaringan IP via IdentityVelocityRiskScorer
        $networkScore = 0;
        if ($this->velocityScorer !== null) {
            $networkScore = $this->velocityScorer->scoreVelocity($userId, $ipAddress, $deviceFingerprint);
            if ($networkScore >= 75) {
                $riskFlags[] = 'NETWORK_IP_RISK_VERY_HIGH';
            }
        }

        // 4. Perilaku Penjualan Sekunder Massal
        if ($secondaryTicketsListedIn24h >= 4) {
            $riskFlags[] = 'BULK_SECONDARY_LISTING_EXCEEDED';
        }

        // Hitung skor komposit berbobot
        $compositeScore = (int) round(
            ($velocityScore * 0.45) + ($deviceScore * 0.20) + ($networkScore * 0.35)
        );

        $isAutoBlacklist = $compositeScore >= 80 || in_array('VELOCITY_FLOOD_HIGH', $riskFlags, true);

        return new ScalperFraudRiskProfileDTO(
            $userId,
            $velocityScore,
            $deviceScore,
            $networkScore,
            min(100, $compositeScore),
            $isAutoBlacklist,
            $riskFlags,
            new \DateTimeImmutable
        );
    }

    /**
     * Otomatisasi pemblokiran akun jika terdeteksi bot berisiko tinggi.
     */
    public function autoEnforceBlacklistIfMalicious(
        ScalperFraudRiskProfileDTO $riskProfile,
        string $ipAddress,
        ?string $deviceFingerprint,
        int $requestsPerMinute
    ): ?ScalperBlacklistLog {
        if (! $riskProfile->isAutoBlacklistRecommended) {
            return null;
        }

        $reason = 'Deteksi otomatis bot scalping: '.implode(', ', $riskProfile->riskFlags);

        return $this->blacklistAction->execute(
            $riskProfile->userId,
            $reason,
            $requestsPerMinute,
            $ipAddress,
            $deviceFingerprint
        );
    }
}
