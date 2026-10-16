<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\InventoryService as InventoryServiceContract;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Resto\Contracts\RestoIngredientStockPort;
use Modules\Store\Contracts\StoreProductStockPort;

class InventoryService implements InventoryServiceContract
{
    public function __construct(
        private readonly ?RestoIngredientStockPort $restoStock = null,
        private readonly ?StoreProductStockPort $storeStock = null,
    ) {}

    private function getRestoStock(): RestoIngredientStockPort
    {
        return $this->restoStock ?? app(RestoIngredientStockPort::class);
    }

    private function getStoreStock(): StoreProductStockPort
    {
        return $this->storeStock ?? app(StoreProductStockPort::class);
    }

    public function available(int $productId): int
    {
        return $this->getStoreStock()->getCachedStock($productId);
    }

    public function reserve(
        int $productId,
        int $qty,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): StockMovement {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah reservasi harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($productId, $qty, $sourceType, $sourceId, $note, $userId) {
            try {
                $this->getStoreStock()->reserveStock($productId, $qty);
            } catch (\RuntimeException $e) {
                throw new InsufficientStockException($e->getMessage(), (int) $e->getCode(), $e);
            }

            return StockMovement::create([
                'product_id' => $productId,
                'qty' => -$qty,
                'reason' => StockMovementReason::RESERVATION,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note ?? 'Reservasi stok untuk checkout',
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    public function commit(
        int $reservationMovementId,
        string|StockMovementReason $reason = StockMovementReason::SALE,
        ?string $note = null
    ): StockMovement {
        $reasonEnum = is_string($reason) ? StockMovementReason::from($reason) : $reason;

        return DB::transaction(function () use ($reservationMovementId, $reasonEnum, $note) {
            /** @var StockMovement $reservation */
            $reservation = StockMovement::where('id', $reservationMovementId)->lockForUpdate()->firstOrFail();

            if ($reservation->reason !== StockMovementReason::RESERVATION) {
                return $reservation;
            }

            // Cek penyelesaian idempoten: bila sudah ada movement penyelesaian, kembalikan movement tersebut
            $existingResolution = StockMovement::where('reference_movement_id', $reservation->id)->first();
            if ($existingResolution !== null) {
                return $existingResolution;
            }

            // Buat baris StockMovement baru yang menautkan reservasi tanpa memutasi baris lama
            return StockMovement::create([
                'product_id' => $reservation->product_id,
                'qty' => $reservation->qty,
                'reason' => $reasonEnum,
                'source_type' => $reservation->source_type,
                'source_id' => $reservation->source_id,
                'reference_movement_id' => $reservation->id,
                'note' => $note ?? "Commit reservasi #{$reservation->id}",
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    public function release(
        int $reservationMovementId,
        ?string $note = null
    ): StockMovement {
        return DB::transaction(function () use ($reservationMovementId, $note) {
            /** @var StockMovement $reservation */
            $reservation = StockMovement::where('id', $reservationMovementId)->lockForUpdate()->firstOrFail();

            if ($reservation->reason !== StockMovementReason::RESERVATION) {
                return $reservation;
            }

            // Cek penyelesaian idempoten: bila sudah pernah diselesaikan, kembalikan resolution tersebut
            $existingResolution = StockMovement::where('reference_movement_id', $reservation->id)->first();
            if ($existingResolution !== null) {
                return $existingResolution;
            }

            $qtyToRelease = abs($reservation->qty);

            $this->getStoreStock()->releaseStock($reservation->product_id, $qtyToRelease);

            return StockMovement::create([
                'product_id' => $reservation->product_id,
                'qty' => $qtyToRelease,
                'reason' => StockMovementReason::RESERVATION_RELEASE,
                'source_type' => $reservation->source_type,
                'source_id' => $reservation->source_id,
                'reference_movement_id' => $reservation->id,
                'note' => $note ?? "Pelepasan reservasi #{$reservation->id}",
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    public function adjust(
        int $productId,
        int $qty,
        string|StockMovementReason $reason,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): StockMovement {
        $reasonEnum = is_string($reason) ? StockMovementReason::from($reason) : $reason;

        return DB::transaction(function () use ($productId, $qty, $reasonEnum, $sourceType, $sourceId, $note, $userId) {
            try {
                $this->getStoreStock()->adjustStock($productId, $qty);
            } catch (\RuntimeException $e) {
                throw new InsufficientStockException($e->getMessage(), (int) $e->getCode(), $e);
            }

            return StockMovement::create([
                'product_id' => $productId,
                'qty' => $qty,
                'reason' => $reasonEnum,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        });
    }

    public function availableIngredient(int $ingredientId, int $outletId): string
    {
        return $this->getRestoStock()->available($ingredientId, $outletId);
    }

    public function deductIngredient(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string|StockMovementReason $reason = StockMovementReason::PRODUCTION,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void {
        $reasonStr = $reason instanceof StockMovementReason ? $reason->value : (string) $reason;
        try {
            $this->getRestoStock()->deduct($ingredientId, $outletId, $qtyBaseUnit, $reasonStr, $sourceType, $sourceId, $note, $userId);
        } catch (\RuntimeException $e) {
            throw new InsufficientStockException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function addIngredient(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string|StockMovementReason $reason = StockMovementReason::PURCHASE,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void {
        $reasonStr = $reason instanceof StockMovementReason ? $reason->value : (string) $reason;
        $this->getRestoStock()->add($ingredientId, $outletId, $qtyBaseUnit, $reasonStr, $sourceType, $sourceId, $note, $userId);
    }

    public function adjustIngredient(
        int $ingredientId,
        int $outletId,
        string $newStockBaseUnit,
        string|StockMovementReason $reason = StockMovementReason::ADJUSTMENT,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): string {
        $reasonStr = $reason instanceof StockMovementReason ? $reason->value : (string) $reason;

        return $this->getRestoStock()->adjust($ingredientId, $outletId, $newStockBaseUnit, $reasonStr, $sourceType, $sourceId, $note, $userId);
    }
}
