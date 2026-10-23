<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Ai;

final readonly class DynamicPricingResult
{
    /**
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(
        public string $itemCode,
        public int $finalPriceIdr,
        public int $basePriceIdr,
        public int $floorPriceIdr,
        public int $ceilingPriceIdr,
        public float $multiplier,
        public array $breakdown,
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
            'final_price_idr' => $this->finalPriceIdr,
            'base_price_idr' => $this->basePriceIdr,
            'floor_price_idr' => $this->floorPriceIdr,
            'ceiling_price_idr' => $this->ceilingPriceIdr,
            'multiplier' => $this->multiplier,
            'breakdown' => $this->breakdown,
            'decision_hash' => $this->decisionHash,
            'decision_code' => $this->decisionCode,
        ];
    }
}
