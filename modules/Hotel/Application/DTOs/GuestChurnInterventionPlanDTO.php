<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class GuestChurnInterventionPlanDTO
{
    /**
     * @param  list<string>  $complimentaryInclusions
     */
    public function __construct(
        public readonly string $guestId,
        public readonly string $guestFullName,
        public readonly float $churnProbability,
        public readonly string $riskCategory,
        public readonly string $interventionType,
        public readonly int $roomDiscountPct,
        public readonly int $bonusLoyaltyPoints,
        public readonly array $complimentaryInclusions,
        public readonly string $assignedManagerRole
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guest_id' => $this->guestId,
            'guest_full_name' => $this->guestFullName,
            'churn_probability' => $this->churnProbability,
            'risk_category' => $this->riskCategory,
            'intervention_type' => $this->interventionType,
            'room_discount_pct' => $this->roomDiscountPct,
            'bonus_loyalty_points' => $this->bonusLoyaltyPoints,
            'complimentary_inclusions' => $this->complimentaryInclusions,
            'assigned_manager_role' => $this->assignedManagerRole,
        ];
    }
}
