<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class ScalperRiskScore
{
    public const THRESHOLD_LOW = 25;

    public const THRESHOLD_MEDIUM = 50;

    public const THRESHOLD_HIGH = 75;

    public const THRESHOLD_CRITICAL = 90;

    public function __construct(
        public readonly int $score
    ) {
        if ($this->score < 0 || $this->score > 100) {
            throw new \InvalidArgumentException('Skor risiko bot harus berada di antara rentang 0 dan 100.');
        }
    }

    public function isBotLikely(): bool
    {
        return $this->score >= self::THRESHOLD_HIGH;
    }

    public function isCriticalThreat(): bool
    {
        return $this->score >= self::THRESHOLD_CRITICAL;
    }

    public function getRiskCategory(): string
    {
        if ($this->score >= self::THRESHOLD_CRITICAL) {
            return 'CRITICAL_BOT_ATTACK';
        }
        if ($this->score >= self::THRESHOLD_HIGH) {
            return 'HIGH_SCALPING_SUSPECT';
        }
        if ($this->score >= self::THRESHOLD_MEDIUM) {
            return 'MEDIUM_VELOCITY_CAUTION';
        }
        if ($this->score >= self::THRESHOLD_LOW) {
            return 'LOW_ANOMALY';
        }

        return 'NORMAL_FAN_ACTIVITY';
    }
}
