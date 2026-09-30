<?php

declare(strict_types=1);

namespace Modules\Inventory\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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

    public function availableIngredient(int $ingredientId, int $outletId): string
    {
        $val = DB::table('resto_ingredient_stocks')
            ->where('ingredient_id', $ingredientId)
            ->where('outlet_id', $outletId)
            ->value('stock_base_unit');

        return $val !== null ? (string) $val : '0.000000';
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
        $reasonEnum = is_string($reason) ? StockMovementReason::from($reason) : $reason;
        $deductAmount = BigDecimal::of($qtyBaseUnit);

        if ($deductAmount->isNegative()) {
            throw new \InvalidArgumentException('Jumlah potongan bahan tidak boleh negatif.');
        }

        if ($deductAmount->isZero()) {
            return;
        }

        DB::transaction(function () use ($ingredientId, $outletId, $deductAmount, $reasonEnum, $sourceType, $sourceId, $note, $userId) {
            $stockRow = DB::table('resto_ingredient_stocks')
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->lockForUpdate()
                ->first();

            $currentStock = $stockRow !== null
                ? BigDecimal::of((string) $stockRow->stock_base_unit)
                : BigDecimal::zero();

            if ($currentStock->isLessThan($deductAmount)) {
                $ingName = DB::table('resto_ingredients')->where('id', $ingredientId)->value('name') ?? "Bahan #{$ingredientId}";
                throw new InsufficientStockException(
                    "Stok bahan '{$ingName}' di outlet #{$outletId} tidak mencukupi. Tersedia: {$currentStock}, dibutuhkan: {$deductAmount}"
                );
            }

            $newStock = $currentStock->minus($deductAmount);

            if ($stockRow !== null) {
                DB::table('resto_ingredient_stocks')
                    ->where('id', $stockRow->id)
                    ->update([
                        'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('resto_ingredient_stocks')->insert([
                    'outlet_id' => $outletId,
                    'ingredient_id' => $ingredientId,
                    'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $deductAmount->negated()->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reasonEnum->value,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        }, attempts: 3);
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
        $reasonEnum = is_string($reason) ? StockMovementReason::from($reason) : $reason;
        $addAmount = BigDecimal::of($qtyBaseUnit);

        if ($addAmount->isNegative()) {
            throw new \InvalidArgumentException('Jumlah penambahan bahan tidak boleh negatif.');
        }

        if ($addAmount->isZero()) {
            return;
        }

        DB::transaction(function () use ($ingredientId, $outletId, $addAmount, $reasonEnum, $sourceType, $sourceId, $note, $userId) {
            $stockRow = DB::table('resto_ingredient_stocks')
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->lockForUpdate()
                ->first();

            $currentStock = $stockRow !== null
                ? BigDecimal::of((string) $stockRow->stock_base_unit)
                : BigDecimal::zero();

            $newStock = $currentStock->plus($addAmount);

            if ($stockRow !== null) {
                DB::table('resto_ingredient_stocks')
                    ->where('id', $stockRow->id)
                    ->update([
                        'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('resto_ingredient_stocks')->insert([
                    'outlet_id' => $outletId,
                    'ingredient_id' => $ingredientId,
                    'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $addAmount->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reasonEnum->value,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        }, attempts: 3);
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
        $reasonEnum = is_string($reason) ? StockMovementReason::from($reason) : $reason;
        $targetStock = BigDecimal::of($newStockBaseUnit);

        if ($targetStock->isNegative()) {
            throw new \InvalidArgumentException('Stok hasil penyesuaian tidak boleh negatif.');
        }

        return DB::transaction(function () use ($ingredientId, $outletId, $targetStock, $reasonEnum, $sourceType, $sourceId, $note, $userId) {
            $stockRow = DB::table('resto_ingredient_stocks')
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->lockForUpdate()
                ->first();

            $currentStock = $stockRow !== null
                ? BigDecimal::of((string) $stockRow->stock_base_unit)
                : BigDecimal::zero();

            $variance = $targetStock->minus($currentStock);

            if ($stockRow !== null) {
                DB::table('resto_ingredient_stocks')
                    ->where('id', $stockRow->id)
                    ->update([
                        'stock_base_unit' => $targetStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('resto_ingredient_stocks')->insert([
                    'outlet_id' => $outletId,
                    'ingredient_id' => $ingredientId,
                    'stock_base_unit' => $targetStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $variance->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reasonEnum->value,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            return $variance->toScale(6, RoundingMode::HalfUp)->__toString();
        }, attempts: 3);
    }
}
