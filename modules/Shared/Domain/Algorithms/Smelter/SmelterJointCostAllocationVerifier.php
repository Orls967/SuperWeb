<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterJointCostAllocationVerifier
{
    public function verify(SmelterJointCostAllocationResult $result, SmelterJointCostInput $input): bool
    {
        if ($result->totalJointCostAllocatedIdr !== $input->jointSmeltingCostIdr) {
            return false;
        }

        if (($result->primaryMetalAllocatedCostIdr + $result->byproductsAllocatedCostIdr) !== $result->totalJointCostAllocatedIdr) {
            return false;
        }

        if (! $result->isConservationBalanced) {
            return false;
        }

        $expectedHash = hash('sha256', sprintf(
            '%d:%d:%d:%d:%d',
            $result->totalJointCostAllocatedIdr,
            $result->primaryMetalAllocatedCostIdr,
            $result->byproductsAllocatedCostIdr,
            $result->primaryMetalUnitCostPerTonIdr,
            1
        ));

        return hash_equals($expectedHash, $result->verificationHash);
    }
}
