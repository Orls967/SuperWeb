<?php

declare(strict_types=1);

namespace Modules\Inventory\Contracts;

use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;

interface InventoryService
{
    /**
     * Get the currently available stock for a product (cached_stock).
     */
    public function available(int $productId): int;

    /**
     * Temporarily reserve stock for checkout. Deducts stock with reason 'reservation'.
     *
     * @throws InsufficientStockException
     */
    public function reserve(
        int $productId,
        int $qty,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): StockMovement;

    /**
     * Commit a previously reserved stock movement into a confirmed deduction (e.g. 'sale').
     */
    public function commit(
        int $reservationMovementId,
        string|StockMovementReason $reason = StockMovementReason::SALE,
        ?string $note = null
    ): StockMovement;

    /**
     * Release a previously reserved stock movement back to available stock.
     */
    public function release(
        int $reservationMovementId,
        ?string $note = null
    ): StockMovement;

    /**
     * Adjust product stock directly (positive or negative) with a given reason.
     *
     * @throws InsufficientStockException
     */
    public function adjust(
        int $productId,
        int $qty,
        string|StockMovementReason $reason,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): StockMovement;
}
