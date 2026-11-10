<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\CostSharingAgreement;
use Modules\Intercompany\Domain\Models\CostSharingAllocation;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;

class AllocateCostSharingAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster
    ) {}

    /**
     * @param array{
     *     agreement_code: string,
     *     period: string,
     *     beneficiary_entity: string,
     *     allocation_ratio_permille: int,
     *     base_cost_idr: int,
     *     operator_id?: int|null
     * } $data
     */
    public function execute(array $data): CostSharingAllocation
    {
        $agreement = CostSharingAgreement::where('agreement_code', $data['agreement_code'])->first();
        if (! $agreement) {
            throw new InvalidArgumentException("Perjanjian pembagian biaya [{$data['agreement_code']}] tidak ditemukan.");
        }

        $beneficiary = IntercompanyEntity::where('entity_code', $data['beneficiary_entity'])->first();
        $provider = $agreement->providerEntity;

        $ratioPermille = (int) $data['allocation_ratio_permille'];
        $baseCostIdr = (int) $data['base_cost_idr'];

        $allocatedCost = (int) floor(($baseCostIdr * $ratioPermille) / 100000);
        $markupAmount = (int) floor(($allocatedCost * $agreement->markup_bps) / 10000);
        $totalBilled = $allocatedCost + $markupAmount;

        $allocation = CostSharingAllocation::create([
            'agreement_id' => $agreement->id,
            'period' => $data['period'],
            'beneficiary_entity_id' => $beneficiary?->id,
            'allocation_ratio_permille' => $ratioPermille,
            'allocated_cost_idr' => $allocatedCost,
            'markup_amount_idr' => $markupAmount,
            'total_billed_idr' => $totalBilled,
        ]);

        $ledgerTx = $this->ledgerPoster->postCostSharingBilling(
            providerEntity: $provider?->entity_code ?? 'HOLDING',
            beneficiaryEntity: $data['beneficiary_entity'],
            totalBilledIdr: $totalBilled,
            agreementCode: $agreement->agreement_code,
            period: $data['period'],
            idempotencyKey: "IC:CS:{$agreement->agreement_code}:{$data['period']}:{$data['beneficiary_entity']}",
            userId: $data['operator_id'] ?? null
        );

        $allocation->ledger_transaction_id = $ledgerTx->id;
        $allocation->save();

        return $allocation;
    }
}
