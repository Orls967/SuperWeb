<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

use Modules\Platform\Domain\Enums\ChangeRiskTier;

final class ChangeRiskAssessment
{
    public function __construct(
        public readonly float $score,
        public readonly ChangeRiskTier $tier,
        public readonly array $factors,
        public readonly string $sha256Signature
    ) {}

    public function requiresCabReview(): bool
    {
        return $this->score > 65.0 || $this->tier === ChangeRiskTier::HIGH || $this->tier === ChangeRiskTier::CRITICAL;
    }
}
