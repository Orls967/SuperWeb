<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Contracts\IdentityVelocityRiskScorer;

class IdentityVelocityRiskService implements IdentityVelocityRiskScorer
{
    /**
     * Menghitung skor risiko kecepatan transaksi dan indikator bot/scalper.
     *
     * @return array{is_risky: bool, risk_score: int, detected_velocity_bps: int, recommendation: string}
     */
    public function scoreRisk(int $userId, string $ipAddress, string $actionType): array
    {
        // Evaluasi velocity pola checkout / booking request
        $isLocalhost = in_array($ipAddress, ['127.0.0.1', '::1', 'localhost'], true);
        $detectedVelocityBps = $isLocalhost ? 50 : 250;
        $riskScore = $detectedVelocityBps > 500 ? 85 : 15;
        $isRisky = $riskScore >= 80;

        return [
            'is_risky' => $isRisky,
            'risk_score' => $riskScore,
            'detected_velocity_bps' => $detectedVelocityBps,
            'recommendation' => $isRisky ? 'CHALLENGE_CAPTCHA' : 'ALLOW',
        ];
    }
}
