<?php

declare(strict_types=1);

namespace Modules\Fleet\Application\Actions;

use Modules\Fleet\Domain\Models\FleetContract;
use Modules\Shared\Application\BaseAction;

class RecognizeMonthlyLeaseInterestAndRouAmortizationAction extends BaseAction
{
    /**
     * Recognize monthly PSAK 73 lease interest expense and ROU asset amortization.
     *
     * @return array{contract: FleetContract, interest_idr: int, principal_idr: int, rou_depreciation_idr: int, remaining_liability_idr: int}
     */
    public function handle(
        int $contractId,
        int $annualIbrBasisPoints = 840
    ): array {
        return $this->transaction(function () use ($contractId, $annualIbrBasisPoints) {
            $contract = FleetContract::where('id', $contractId)->lockForUpdate()->firstOrFail();

            $remainingLiability = $contract->remaining_liability_idr;
            if ($remainingLiability <= 0) {
                return [
                    'contract' => $contract,
                    'interest_idr' => 0,
                    'principal_idr' => 0,
                    'rou_depreciation_idr' => 0,
                    'remaining_liability_idr' => 0,
                ];
            }

            $monthlyRate = ($annualIbrBasisPoints / 10000.0) / 12.0;
            $interestIdr = (int) round($remainingLiability * $monthlyRate);
            $rental = $contract->monthly_rental_idr;

            $principalIdr = max(0, min($remainingLiability, $rental - $interestIdr));
            $rouDepreciationIdr = (int) round($contract->present_value_liability_idr / max(1, $contract->duration_months));

            $newRemaining = max(0, $remainingLiability - $principalIdr);
            $newAmortized = $contract->accumulated_amortized_idr + $principalIdr;

            $contract->remaining_liability_idr = $newRemaining;
            $contract->accumulated_amortized_idr = $newAmortized;
            $contract->save();

            $idempotencyKey = hash('sha256', "fleet:psak73:amortize:{$contract->id}:{$contract->accumulated_amortized_idr}");

            $this->audit(
                action: 'fleet.psak73.amortized',
                auditable: $contract,
                oldValues: ['remaining_liability_idr' => $remainingLiability],
                newValues: [
                    'remaining_liability_idr' => $newRemaining,
                    'interest_idr' => $interestIdr,
                    'principal_idr' => $principalIdr,
                    'rou_depreciation_idr' => $rouDepreciationIdr,
                ],
                correlationId: "psak73_amort_{$contract->contract_number}",
                impactType: 'psak73_schedule'
            );

            $this->outbox(
                eventType: 'FleetLeaseAmortizationRecognized',
                payload: [
                    'contract_id' => $contract->id,
                    'contract_number' => $contract->contract_number,
                    'interest_idr' => $interestIdr,
                    'principal_idr' => $principalIdr,
                    'rou_depreciation_idr' => $rouDepreciationIdr,
                    'remaining_liability_idr' => $newRemaining,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'contract' => $contract,
                'interest_idr' => $interestIdr,
                'principal_idr' => $principalIdr,
                'rou_depreciation_idr' => $rouDepreciationIdr,
                'remaining_liability_idr' => $newRemaining,
            ];
        });
    }
}
