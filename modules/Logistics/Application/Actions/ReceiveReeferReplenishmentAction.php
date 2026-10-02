<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\InventoryService;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Logistics\Domain\Models\Shipment;

/**
 * Handles receipt of stock for cold-chain replenishment (e.g. from CK-01).
 * Idempotent: stock is only increased once per shipment.
 */
class ReceiveReeferReplenishmentAction
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function execute(Shipment $shipment, int $productId, int $qty, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($shipment, $productId, $qty, $userId) {
            // Check if already received for this shipment to maintain idempotency
            $existing = StockMovement::where('source_type', 'lgx_shipment')
                ->where('source_id', $shipment->id)
                ->where('product_id', $productId)
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->inventoryService->adjust(
                productId: $productId,
                qty: $qty,
                reason: StockMovementReason::PURCHASE,
                sourceType: 'lgx_shipment',
                sourceId: $shipment->id,
                note: "Penerimaan replenishment reefer {$shipment->tracking_number}",
                userId: $userId,
            );
        });
    }
}
