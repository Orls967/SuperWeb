<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Events;

/**
 * Event domain: warehouse.transfer.received.v1
 */
final readonly class WarehouseTransferReceivedEvent
{
    public const string TOPIC = 'warehouse.transfer.received.v1';

    public function __construct(
        public int $transferId,
        public int $fromWarehouseId,
        public int $toWarehouseId,
        public int $totalValueIdr,
        public string $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'event' => self::TOPIC,
            'transfer_id' => $this->transferId,
            'from_warehouse_id' => $this->fromWarehouseId,
            'to_warehouse_id' => $this->toWarehouseId,
            'total_value_idr' => $this->totalValueIdr,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
