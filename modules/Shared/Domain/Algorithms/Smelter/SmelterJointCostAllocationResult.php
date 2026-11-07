<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

final class SmelterJointCostAllocationResult
{
    /**
     * @param  array<string, array<string, mixed>>  $productAllocations
     */
    public function __construct(
        public readonly int $totalJointCostAllocatedIdr,
        public readonly int $primaryMetalAllocatedCostIdr,
        public readonly int $primaryMetalUnitCostPerTonIdr,
        public readonly int $byproductsAllocatedCostIdr,
        public readonly int $clearingDiscrepancyIdr,
        public readonly array $productAllocations,
        public readonly bool $isConservationBalanced,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_joint_cost_allocated_idr' => $this->totalJointCostAllocatedIdr,
            'primary_metal_allocated_cost_idr' => $this->primaryMetalAllocatedCostIdr,
            'primary_metal_unit_cost_per_ton_idr' => $this->primaryMetalUnitCostPerTonIdr,
            'byproducts_allocated_cost_idr' => $this->byproductsAllocatedCostIdr,
            'clearing_discrepancy_idr' => $this->clearingDiscrepancyIdr,
            'product_allocations' => $this->productAllocations,
            'is_conservation_balanced' => $this->isConservationBalanced,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
