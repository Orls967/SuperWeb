<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Events;

/**
 * Event domain: warehouse.stock.adjusted.v1
 */
final readonly class WarehouseStockAdjustedEvent
{
    public const string TOPIC = 'warehouse.stock.adjusted.v1';

    public function __construct(
        public int $warehouseId,
        public int $productId,
        public int $deltaQtyBase,
        public int $deltaValueIdr,
        public string $reason,
        public string $occurredAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'event' => self::TOPIC,
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
            'delta_qty_base' => $this->deltaQtyBase,
            'delta_value_idr' => $this->deltaValueIdr,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
