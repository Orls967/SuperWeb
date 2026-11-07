<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Ledger\MiningFase121LedgerPostingService;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostAllocationResult;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostAllocationVerifier;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointCostInput;
use Modules\Shared\Domain\Algorithms\Smelter\SmelterJointProductionCostNrvAllocator;

final class AllocateSmelterJointCostsAction
{
    public function __construct(
        private readonly SmelterJointProductionCostNrvAllocator $allocator,
        private readonly SmelterJointCostAllocationVerifier $verifier,
        private readonly MiningFase121LedgerPostingService $ledgerPostingService,
    ) {}

    public function execute(string $furnaceRunCode, SmelterJointCostInput $input): SmelterJointCostAllocationResult
    {
        $run = MiningSmelterFurnaceRun::query()->where('run_code', $furnaceRunCode)->first();
        if (! $run) {
            throw new DomainException("Run tanur smelter {$furnaceRunCode} tidak ditemukan.");
        }

        $result = $this->allocator->allocate($input);

        $isValid = $this->verifier->verify($result, $input);
        if (! $isValid) {
            throw new DomainException("Verifikasi alokasi biaya bersama (NRV) untuk run {$furnaceRunCode} tidak lolos.");
        }

        // Post ledger entries: debit inventory, credit joint cost clearing
        $journalId = $this->ledgerPostingService->postJointCostAllocation(
            runCode: $furnaceRunCode,
            totalJointCostIdr: $result->totalJointCostAllocatedIdr,
            primaryMetalCostIdr: $result->primaryMetalAllocatedCostIdr,
            byproductsCostIdr: $result->byproductsAllocatedCostIdr,
            description: "Alokasi biaya bersama NRV leburan {$furnaceRunCode}"
        );

        $meta = (array) ($run->metadata ?? []);
        $meta['joint_cost_allocation'] = $result->toArray();
        $meta['joint_cost_journal_id'] = $journalId;
        $run->update(['metadata' => $meta]);

        return $result;
    }
}
