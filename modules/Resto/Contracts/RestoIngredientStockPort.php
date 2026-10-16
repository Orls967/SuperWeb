<?php

declare(strict_types=1);

namespace Modules\Resto\Contracts;

interface RestoIngredientStockPort
{
    /**
     * Get available stock for an ingredient in a specific outlet (in base unit, e.g. gram/ml/pcs).
     */
    public function available(int $ingredientId, int $outletId): string;

    /**
     * Deduct ingredient stock for production/service usage or waste.
     */
    public function deduct(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string $reason = 'production',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void;

    /**
     * Add ingredient stock for purchase, goods receipt, or transfer.
     */
    public function add(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string $reason = 'purchase',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void;

    /**
     * Adjust ingredient stock to an actual counted quantity (stock opname).
     * Returns the signed variance (counted - current).
     */
    public function adjust(
        int $ingredientId,
        int $outletId,
        string $newStockBaseUnit,
        string $reason = 'adjustment',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): string;
}
