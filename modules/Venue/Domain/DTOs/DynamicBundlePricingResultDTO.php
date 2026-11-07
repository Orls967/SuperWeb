<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class DynamicBundlePricingResultDTO
{
    public function __construct(
        public readonly int $totalBasePriceIdr,
        public readonly int $bundlePriceIdr,
        public readonly int $effectiveDiscountBps,
        public readonly int $totalSavingsIdr,
        public readonly int $floorPriceGuardrailIdr,
        public readonly bool $isFloorGuardrailTriggered,
        public readonly string $algorithmSignature,
        public readonly \DateTimeImmutable $computedAt,
    ) {
        if ($this->totalBasePriceIdr <= 0) {
            throw new \InvalidArgumentException('totalBasePriceIdr harus positif');
        }
        if ($this->bundlePriceIdr <= 0) {
            throw new \InvalidArgumentException('bundlePriceIdr harus positif');
        }
        if ($this->bundlePriceIdr < $this->floorPriceGuardrailIdr) {
            throw new \InvalidArgumentException('bundlePriceIdr melanggar floorPriceGuardrailIdr');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_base_price_idr' => $this->totalBasePriceIdr,
            'bundle_price_idr' => $this->bundlePriceIdr,
            'effective_discount_bps' => $this->effectiveDiscountBps,
            'total_savings_idr' => $this->totalSavingsIdr,
            'floor_price_guardrail_idr' => $this->floorPriceGuardrailIdr,
            'is_floor_guardrail_triggered' => $this->isFloorGuardrailTriggered,
            'algorithm_signature' => $this->algorithmSignature,
            'computed_at' => $this->computedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
