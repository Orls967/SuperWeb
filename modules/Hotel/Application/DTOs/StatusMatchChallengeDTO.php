<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class StatusMatchChallengeDTO
{
    /**
     * @param  list<string>  $requiredProofDocuments
     */
    public function __construct(
        public string $matchRequestId,
        public string $travelPassId,
        public string $competitorProgram,
        public string $competitorTier,
        public string $grantedTier,
        public int $requiredNightsToRetain,
        public string $trialExpiry,
        public string $status,
        public array $requiredProofDocuments,
        public string $appliedAt
    ) {}
}
