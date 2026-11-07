<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use DomainException;
use Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasing;
use Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasingCertificateVerifier;
use Modules\Shared\Domain\Algorithms\Scheduling\WeightedTimePhasingResult;
use PHPUnit\Framework\TestCase;

class WeightedTimePhasingTest extends TestCase
{
    public function test_weighted_time_phasing_prompt_vector(): void
    {
        $algorithm = new WeightedTimePhasing();

        $totalTarget = 100003;
        $weights = [5, 5, 5, 5, 4, 4, 2];

        $result = $algorithm->phase($totalTarget, $weights);

        $expectedDaily = [16667, 16667, 16667, 16667, 13334, 13334, 6667];
        $expectedCumulative = [16667, 33334, 50001, 66668, 80002, 93336, 100003];

        $this->assertEquals($expectedDaily, $result->dailyAllocations);
        $this->assertEquals($expectedCumulative, $result->cumulativeSCurve);
        $this->assertEquals($totalTarget, array_sum($result->dailyAllocations));
    }

    public function test_corrupted_certificate_throws_domain_exception(): void
    {
        $verifier = new WeightedTimePhasingCertificateVerifier();

        $corruptResult = new WeightedTimePhasingResult(
            totalTarget: 100003,
            dailyAllocations: [16667, 16667, 16667, 16667, 13334, 13334, 6666], // sum = 100002 != 100003
            cumulativeSCurve: [16667, 33334, 50001, 66668, 80002, 93336, 100002],
            exactQuotas: [16667.1666, 16667.1666, 16667.1666, 16667.1666, 13333.7333, 13333.7333, 6666.8666],
            certificateHash: 'invalid_hash',
            certificateData: []
        );

        $this->expectException(DomainException::class);
        $verifier->verify($corruptResult);
    }
}

