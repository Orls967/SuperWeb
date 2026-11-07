<?php

declare(strict_types=1);

namespace Tests\Unit\MiningAlgorithms;

use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostAllocationVerifier;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostInput;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointProductionCostNrvAllocator;
use Tests\TestCase;

final class SmelterJointCostAllocatorTest extends TestCase
{
    public function test_joint_production_cost_nrv_allocator_balances_perfectly(): void
    {
        $allocator = new SmelterJointProductionCostNrvAllocator;
        $verifier = new SmelterJointCostAllocationVerifier;

        $input = new SmelterJointCostInput(
            jointSmeltingCostIdr: 2500000000,
            primaryMetalOutputTons: 20.0,
            primaryMetalMarketPriceIdr: 280000000,
            granulatedSlagOutputTons: 90.0,
            granulatedSlagMarketPriceIdr: 400000,
            crudeSulfurOutputTons: 3.0,
            crudeSulfurMarketPriceIdr: 1500000,
            metalFlueDustOutputTons: 1.2,
            metalFlueDustMarketPriceIdr: 800000
        );

        $result = $allocator->allocate($input);

        $this->assertEquals(2500000000, $result->totalJointCostAllocatedIdr);
        $this->assertTrue($result->isConservationBalanced);
        $this->assertEquals(0, $result->clearingDiscrepancyIdr);
        $this->assertGreaterThan(0, $result->primaryMetalAllocatedCostIdr);
        $this->assertGreaterThan(0, $result->byproductsAllocatedCostIdr);

        $this->assertTrue($verifier->verify($result, $input));
    }
}
