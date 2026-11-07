<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Waitlist;

final readonly class WaitlistGovernorResult
{
    /**
     * @param  array<int, int>  $rankedUserIds
     * @param  array<int, int>  $rankedScores
     */
    public function __construct(
        public array $rankedUserIds,
        public array $rankedScores,
        public ?WaitlistPriorityCertificate $certificate = null,
    ) {}
}
