<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Waitlist;

final readonly class WaitlistGovernorInput
{
    /**
     * @param  array<int, array{user_id: int, tier: string, spend_365_idr: int, registered_seconds_ago: int}>  $candidates
     */
    public function __construct(
        public array $candidates,
    ) {}
}
