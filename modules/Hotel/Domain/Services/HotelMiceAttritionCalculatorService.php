<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\DTO\MiceAttritionCalculationDTO;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\ValueObjects\MiceAttritionPolicy;

final class HotelMiceAttritionCalculatorService
{
    public function __construct(
        private readonly MiceAttritionPolicy $policy = new MiceAttritionPolicy
    ) {}

    public function evaluateAttrition(string $contractId): MiceAttritionCalculationDTO
    {
        $contract = MiceContract::findOrFail($contractId);
        $allotments = RoomBlockAllotment::where('mice_contract_id', $contract->id)->get();

        $contractedNights = 0;
        $pickedUpNights = 0;
        $avgRate = 1200000.0;

        foreach ($allotments as $allotment) {
            $allocated = (int) ($allotment->allotted_rooms_count ?? 0);
            $picked = (int) ($allotment->confirmed_rooms_count ?? 0);

            $contractedNights += $allocated;
            $pickedUpNights += $picked;
        }

        $tolerancePct = $this->policy->allowedSlippagePercent;
        $minimumPickupCommitment = (int) ceil($contractedNights * (1.0 - ($tolerancePct / 100.0)));

        $shortfall = max(0, $minimumPickupCommitment - $pickedUpNights);
        $penalty = $this->policy->calculatePenalty($contractedNights, $pickedUpNights, $avgRate);
        $isPenaltyApplicable = $shortfall > 0;

        return new MiceAttritionCalculationDTO(
            contractNumber: (string) ($contract->event_code ?? 'MICE-UNKNOWN'),
            contractedRoomNights: $contractedNights,
            minimumPickupCommitmentNights: $minimumPickupCommitment,
            actualPickedUpRoomNights: $pickedUpNights,
            shortfallNights: $shortfall,
            contractedRoomRate: round($avgRate, 2),
            slippageTolerancePercent: $tolerancePct,
            attritionPenaltyAmount: (int) round($penalty),
            isPenaltyApplicable: $isPenaltyApplicable,
            slippageDetails: [
                'allotment_count' => count($allotments),
                'pickup_percentage' => $contractedNights > 0 ? round(($pickedUpNights / $contractedNights) * 100, 2) : 0.0,
                'audit_timestamp' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
