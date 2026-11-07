<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms;

final readonly class MembershipTierInput
{
    public function __construct(
        public string $currentTier, // 'sun', 'moon', 'infinity'
        public int $spend365DaysIdr,
        public int $visits365Days,
        public bool $isAnniversaryEvaluation = false,
        public int $spendCurrentYearIdr = 0,
    ) {}
}
