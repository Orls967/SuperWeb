<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event resmi diterbitkan saat kontrak lindung nilai (Forward Contract)
 * mencapai tanggal jatuh tempo (status MATURED) untuk instruksi penyelesaian
 * fisik maupun net settlement (EventCatalog: treasury.hedge.matured.v1).
 */
final class TreasuryHedgeMaturedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.hedge.matured.v1';

    public function __construct(
        public readonly string $contractId,
        public readonly string $contractNumber,
        public readonly string $currency,
        public readonly int $notionalForeignAmount,
        public readonly string $maturityDate,
        public readonly int $mtmValueIdr = 0,
        public readonly int $forwardRateScaled = 0,
        public readonly ?int $actorId = null
    ) {}

    /**
     * @return array<string, int|string|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'contract_id' => $this->contractId,
            'contract_number' => $this->contractNumber,
            'currency' => $this->currency,
            'notional_foreign_amount' => $this->notionalForeignAmount,
            'maturity_date' => $this->maturityDate,
            'mtm_value_idr' => $this->mtmValueIdr,
            'forward_rate_scaled' => $this->forwardRateScaled,
            'actor_id' => $this->actorId,
        ];
    }
}
