<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Contracts\InventoryService as InventoryServiceContract;
use Modules\Inventory\Domain\Enums\StockMovementReason;
use Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Modules\Inventory\Domain\Models\StockMovement;
use Modules\Store\Domain\Models\Product;

class InventoryService implements InventoryServiceContract
{
    public function available(int $productId): int
    {
        return (int) (Product::where('id', $productId)->value('cached_stock') ?? 0);
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
            /** @var Product $product */
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            if ($product->cached_stock < $qty) {
                throw new InsufficientStockException(
                    "Stok tidak mencukupi untuk {$product->name}. Tersedia: {$product->cached_stock}, diminta: {$qty}"
                );
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'qty' => -$qty,
                'reason' => StockMovementReason::RESERVATION,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note ?? 'Reservasi stok untuk checkout',
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            $product->cached_stock -= $qty;
            $product->save();

            return $movement;
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

            $reservation->reason = $reasonEnum;
            if ($note !== null) {
                $reservation->note = $note;
            }
            $reservation->save();

            return $reservation;
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

            $qtyToRelease = abs($reservation->qty);

            /** @var Product $product */
            $product = Product::where('id', $reservation->product_id)->lockForUpdate()->firstOrFail();

            $releaseMovement = StockMovement::create([
                'product_id' => $product->id,
                'qty' => $qtyToRelease,
                'reason' => StockMovementReason::RESERVATION_RELEASE,
                'source_type' => $reservation->source_type,
                'source_id' => $reservation->source_id,
                'note' => $note ?? "Pelepasan reservasi #{$reservation->id}",
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $product->cached_stock += $qtyToRelease;
            $product->save();

            // Mark reservation note as released
            $reservation->note = ($reservation->note ? $reservation->note.' | ' : '').'RELEASED';
            $reservation->save();

            return $releaseMovement;
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
            /** @var Product $product */
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $newStock = $product->cached_stock + $qty;
            if ($newStock < 0) {
                throw new InsufficientStockException(
                    "Stok tidak mencukupi untuk {$product->name}. Tersedia: {$product->cached_stock}, perubahan: {$qty}"
                );
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'qty' => $qty,
                'reason' => $reasonEnum,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            $product->cached_stock = $newStock;
            $product->save();

            return $movement;
        });
    }
}
