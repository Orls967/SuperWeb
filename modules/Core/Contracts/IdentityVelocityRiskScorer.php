<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface IdentityVelocityRiskScorer
{
    /**
     * Menghitung skor risiko kecepatan transaksi dan indikator bot/scalper.
     *
     * @return array{is_risky: bool, risk_score: int, detected_velocity_bps: int, recommendation: string}
     */
    public function scoreRisk(int $userId, string $ipAddress, string $actionType): array;
}
