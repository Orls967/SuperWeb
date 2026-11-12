<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Events;

final class EnterprisePeriodClosed
{
    public const EVENT_NAME = 'enterprise.period.closed.v1';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $entityCode,
        public readonly string $period,
        public readonly int $netIncomeLossMinor,
        public readonly string $closingVoucherId,
        public readonly array $metadata = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'entity_code' => $this->entityCode,
            'period' => $this->period,
            'net_income_loss_minor' => $this->netIncomeLossMinor,
            'closing_voucher_id' => $this->closingVoucherId,
            'metadata' => $this->metadata,
        ];
    }
}
