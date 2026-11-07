<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistGovernorInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistPriorityGovernor;
use Tests\TestCase;

final class WaitlistPriorityGovernorTest extends TestCase
{
    private WaitlistPriorityGovernor $governor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->governor = new WaitlistPriorityGovernor;
    }

    public function test_prioritizes_higher_tier_members_and_spenders(): void
    {
        $candidates = [
            ['user_id' => 1, 'tier' => 'Reguler', 'spend_365_idr' => 1000000, 'registered_seconds_ago' => 600],
            ['user_id' => 2, 'tier' => 'Moon', 'spend_365_idr' => 10000000, 'registered_seconds_ago' => 100],
            ['user_id' => 3, 'tier' => 'Infinity', 'spend_365_idr' => 5000000, 'registered_seconds_ago' => 50],
        ];

        // Scores:
        // User 3 (Infinity): 100,000 + 5,000 + 5 = 105,005 -> Rank 1
        // User 2 (Moon): 50,000 + 10,000 + 10 = 60,010 -> Rank 2
        // User 1 (Reguler): 0 + 1,000 + 60 = 1,060 -> Rank 3

        $result = $this->governor->rankCandidates(new WaitlistGovernorInput($candidates));

        $this->assertSame([3, 2, 1], $result->rankedUserIds);
        $this->assertSame([105005, 60010, 1060], $result->rankedScores);
        $this->assertTrue($result->certificate?->isValid());
    }

    public function test_seniority_breaks_ties_for_equal_tier_and_spending(): void
    {
        $candidates = [
            ['user_id' => 10, 'tier' => 'Sun', 'spend_365_idr' => 2000000, 'registered_seconds_ago' => 120], // 25,000 + 2,000 + 12 = 27,012
            ['user_id' => 11, 'tier' => 'Sun', 'spend_365_idr' => 2000000, 'registered_seconds_ago' => 300], // 25,000 + 2,000 + 30 = 27,030
        ];

        $result = $this->governor->rankCandidates(new WaitlistGovernorInput($candidates));

        $this->assertSame([11, 10], $result->rankedUserIds);
        $this->assertSame([27030, 27012], $result->rankedScores);
        $this->assertTrue($result->certificate?->isValid());
    }
}
