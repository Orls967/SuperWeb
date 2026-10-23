<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\PharmacyBranch;

class PharmacyMultiBranchRoutingService
{
    public function __construct(
        private readonly FefoInventoryService $fefoService,
    ) {}

    /**
     * Locate the most suitable pharmacy branch based on locality and FEFO availability
     */
    public function findBestBranch(string $patientCity, string $drugCode, int $quantity): ?PharmacyBranch
    {
        // 1. Prioritize branches in the exact same city
        $localBranches = PharmacyBranch::where('is_active', true)
            ->where('city', $patientCity)
            ->get();

        foreach ($localBranches as $branch) {
            $stock = $this->fefoService->getAvailableStock($branch->id, $drugCode);
            if ($stock >= $quantity) {
                return $branch;
            }
        }

        // 2. Fallback to regional network branches
        $otherBranches = PharmacyBranch::where('is_active', true)
            ->where('city', '!=', $patientCity)
            ->get();

        foreach ($otherBranches as $branch) {
            $stock = $this->fefoService->getAvailableStock($branch->id, $drugCode);
            if ($stock >= $quantity) {
                return $branch;
            }
        }

        return null;
    }
}
