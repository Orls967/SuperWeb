<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat fasilitas kredit ditarik (drawdown) dan diposting ke ledger.
 */
final class FacilityDrawdownExecutedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.facility.drawdown_executed.v1';

    public function __construct(
        public string $facilityId,
        public string $facilityCode,
        public string $bankName,
        public int $drawdownAmountMinor,
        public string $currency,
        public int $newTotalDrawnMinor,
        public int $currentDerPermille,
        public int $maxDerPermille,
        public ?int $actorId = null
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'facility_id' => $this->facilityId,
            'facility_code' => $this->facilityCode,
            'bank_name' => $this->bankName,
            'drawdown_amount_minor' => $this->drawdownAmountMinor,
            'currency' => $this->currency,
            'new_total_drawn_minor' => $this->newTotalDrawnMinor,
            'current_der_permille' => $this->currentDerPermille,
            'max_der_permille' => $this->maxDerPermille,
            'actor_id' => $this->actorId,
        ];
    }
}
