<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final readonly class PersonalizedPromoDispatchDTO
{
    public function __construct(
        public int $userId,
        public int $zoneId,
        public string $promoTitle,
        public int $discountBps,
        public int $discountedPriceIdr,
        public int $savingsIdr,
        public bool $floorGuardrailActive,
        public string $dispatchCode,
        public int $deliveredAtTimestamp
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'zone_id' => $this->zoneId,
            'promo_title' => $this->promoTitle,
            'discount_bps' => $this->discountBps,
            'discounted_price_idr' => $this->discountedPriceIdr,
            'savings_idr' => $this->savingsIdr,
            'floor_guardrail_active' => $this->floorGuardrailActive,
            'dispatch_code' => $this->dispatchCode,
            'delivered_at_timestamp' => $this->deliveredAtTimestamp,
        ];
    }
}
