<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

final class MicrogridLoadSheddingResult
{
    /**
     * @param  list<array<string, mixed>>  $retainedFeeders
     * @param  list<array<string, mixed>>  $shedFeeders
     */
    public function __construct(
        public readonly int $availableGenerationKw,
        public readonly int $totalServedKw,
        public readonly int $totalShedKw,
        public readonly int $totalUtilityScore,
        public readonly array $retainedFeeders,
        public readonly array $shedFeeders,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}
}
