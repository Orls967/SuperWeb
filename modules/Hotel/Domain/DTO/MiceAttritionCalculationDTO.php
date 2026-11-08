<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class MiceAttritionCalculationDTO
{
    /**
     * @param  array<string, mixed>  $slippageDetails
     */
    public function __construct(
        public readonly string $contractNumber,
        public readonly int $contractedRoomNights,
        public readonly int $minimumPickupCommitmentNights, // e.g. 85% of contracted
        public readonly int $actualPickedUpRoomNights,
        public readonly int $shortfallNights,
        public readonly float $contractedRoomRate,
        public readonly float $slippageTolerancePercent,
        public readonly int $attritionPenaltyAmount,
        public readonly bool $isPenaltyApplicable,
        public readonly array $slippageDetails = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract_number' => $this->contractNumber,
            'contracted_room_nights' => $this->contractedRoomNights,
            'minimum_pickup_commitment_nights' => $this->minimumPickupCommitmentNights,
            'actual_picked_up_room_nights' => $this->actualPickedUpRoomNights,
            'shortfall_nights' => $this->shortfallNights,
            'contracted_room_rate' => $this->contractedRoomRate,
            'slippage_tolerance_percent' => $this->slippageTolerancePercent,
            'attrition_penalty_amount' => $this->attritionPenaltyAmount,
            'is_penalty_applicable' => $this->isPenaltyApplicable,
            'slippage_details' => $this->slippageDetails,
        ];
    }
}
