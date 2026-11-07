<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class ScalperFraudRiskProfileDTO
{
    /**
     * @param  array<int, string>  $riskFlags
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $velocityScore,
        public readonly int $deviceAnomalyScore,
        public readonly int $networkReputationScore,
        public readonly int $compositeRiskScore,
        public readonly bool $isAutoBlacklistRecommended,
        public readonly array $riskFlags,
        public readonly \DateTimeImmutable $evaluatedAt,
    ) {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('userId harus positif');
        }
        if ($this->compositeRiskScore < 0 || $this->compositeRiskScore > 100) {
            throw new \InvalidArgumentException('compositeRiskScore harus antara 0 dan 100');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'velocity_score' => $this->velocityScore,
            'device_anomaly_score' => $this->deviceAnomalyScore,
            'network_reputation_score' => $this->networkReputationScore,
            'composite_risk_score' => $this->compositeRiskScore,
            'is_auto_blacklist_recommended' => $this->isAutoBlacklistRecommended,
            'risk_flags' => $this->riskFlags,
            'evaluated_at' => $this->evaluatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
