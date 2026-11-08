<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class BanquetBeoProductionDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $ingredientRequirements
     * @param  array<int, array<string, mixed>>  $serviceTimeline
     * @param  array<string, mixed>  $staffingAllocation
     */
    public function __construct(
        public readonly string $beoNumber,
        public readonly string $eventName,
        public readonly string $eventDate,
        public readonly int $guaranteedPax,
        public readonly int $bufferPax,
        public readonly int $totalPreparedPax,
        public readonly array $ingredientRequirements,
        public readonly int $totalFoodCost,
        public readonly int $totalBeverageCost,
        public readonly int $totalLaborCost,
        public readonly array $serviceTimeline,
        public readonly array $staffingAllocation,
        public readonly string $kitchenNotes = ''
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'beo_number' => $this->beoNumber,
            'event_name' => $this->eventName,
            'event_date' => $this->eventDate,
            'guaranteed_pax' => $this->guaranteedPax,
            'buffer_pax' => $this->bufferPax,
            'total_prepared_pax' => $this->totalPreparedPax,
            'ingredient_requirements' => $this->ingredientRequirements,
            'total_food_cost' => $this->totalFoodCost,
            'total_beverage_cost' => $this->totalBeverageCost,
            'total_labor_cost' => $this->totalLaborCost,
            'service_timeline' => $this->serviceTimeline,
            'staffing_allocation' => $this->staffingAllocation,
            'kitchen_notes' => $this->kitchenNotes,
        ];
    }
}
