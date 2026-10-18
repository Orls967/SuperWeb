<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Ai;

final readonly class StockRecommendationResult
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $itemCode,
        public string $itemName,
        public int $currentStock,
        public int $calculatedRop,
        public int $safetyStock,
        public int $suggestedOrderQty,
        public float $seasonalFactor,
        public int $estimatedCostIdr,
        public bool $requiresHumanApproval,
        public string $status,
        public array $parameters,
        public string $decisionHash,
        public string $decisionCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'current_stock' => $this->currentStock,
            'calculated_rop' => $this->calculatedRop,
            'safety_stock' => $this->safetyStock,
            'suggested_order_qty' => $this->suggestedOrderQty,
            'seasonal_factor' => $this->seasonalFactor,
            'estimated_cost_idr' => $this->estimatedCostIdr,
            'requires_human_approval' => $this->requiresHumanApproval,
            'status' => $this->status,
            'parameters' => $this->parameters,
            'decision_hash' => $this->decisionHash,
            'decision_code' => $this->decisionCode,
        ];
    }
}
