<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\SeafoodTraceabilityMerkleService;

class GenerateTraceabilityProofAction
{
    protected SeafoodTraceabilityMerkleService $traceabilityService;

    public function __construct(SeafoodTraceabilityMerkleService $traceabilityService)
    {
        $this->traceabilityService = $traceabilityService;
    }

    /**
     * @return array{
     *     lot_number: string,
     *     merkle_root_hash: string,
     *     leaf_count: int,
     *     tree_depth: int
     * }
     */
    public function execute(int $exportLotId): array
    {
        return $this->traceabilityService->generateLotMerkleTree($exportLotId);
    }
}
