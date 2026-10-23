<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Resto\Contracts\RestoIngredientStockPort;

class RestoIngredientStockService implements RestoIngredientStockPort
{
    public function available(int $ingredientId, int $outletId): string
    {
        $val = DB::table('resto_ingredient_stocks')
            ->where('ingredient_id', $ingredientId)
            ->where('outlet_id', $outletId)
            ->value('stock_base_unit');

        return $val !== null ? BigDecimal::of((string) $val)->toScale(6, RoundingMode::HalfUp)->__toString() : '0.000000';
    }

    public function deduct(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string $reason = 'production',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void {
        $deductAmount = BigDecimal::of($qtyBaseUnit);

        if ($deductAmount->isNegative()) {
            throw new \InvalidArgumentException('Jumlah potongan bahan tidak boleh negatif.');
        }

        if ($deductAmount->isZero()) {
            return;
        }

        DB::transaction(function () use ($ingredientId, $outletId, $deductAmount, $reason, $sourceType, $sourceId, $note, $userId) {
            DB::table('resto_ingredient_stocks')->insertOrIgnore([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'stock_base_unit' => '0.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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
                throw new \RuntimeException(
                    "Stok bahan '{$ingName}' di outlet #{$outletId} tidak mencukupi. Tersedia: {$currentStock}, dibutuhkan: {$deductAmount}"
                );
            }

            $newStock = $currentStock->minus($deductAmount);

            DB::table('resto_ingredient_stocks')
                ->where('id', $stockRow->id)
                ->update([
                    'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'updated_at' => now(),
                ]);

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $deductAmount->negated()->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function add(
        int $ingredientId,
        int $outletId,
        string $qtyBaseUnit,
        string $reason = 'purchase',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): void {
        $addAmount = BigDecimal::of($qtyBaseUnit);

        if ($addAmount->isNegative()) {
            throw new \InvalidArgumentException('Jumlah penambahan bahan tidak boleh negatif.');
        }

        if ($addAmount->isZero()) {
            return;
        }

        DB::transaction(function () use ($ingredientId, $outletId, $addAmount, $reason, $sourceType, $sourceId, $note, $userId) {
            DB::table('resto_ingredient_stocks')->insertOrIgnore([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'stock_base_unit' => '0.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stockRow = DB::table('resto_ingredient_stocks')
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->lockForUpdate()
                ->first();

            $currentStock = $stockRow !== null
                ? BigDecimal::of((string) $stockRow->stock_base_unit)
                : BigDecimal::zero();

            $newStock = $currentStock->plus($addAmount);

            DB::table('resto_ingredient_stocks')
                ->where('id', $stockRow->id)
                ->update([
                    'stock_base_unit' => $newStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'updated_at' => now(),
                ]);

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $addAmount->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'note' => $note,
                'created_by' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function adjust(
        int $ingredientId,
        int $outletId,
        string $newStockBaseUnit,
        string $reason = 'adjustment',
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?int $userId = null
    ): string {
        $targetStock = BigDecimal::of($newStockBaseUnit);

        if ($targetStock->isNegative()) {
            throw new \InvalidArgumentException('Stok hasil penyesuaian tidak boleh negatif.');
        }

        return DB::transaction(function () use ($ingredientId, $outletId, $targetStock, $reason, $sourceType, $sourceId, $note, $userId) {
            DB::table('resto_ingredient_stocks')->insertOrIgnore([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'stock_base_unit' => '0.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stockRow = DB::table('resto_ingredient_stocks')
                ->where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->lockForUpdate()
                ->first();

            $currentStock = $stockRow !== null
                ? BigDecimal::of((string) $stockRow->stock_base_unit)
                : BigDecimal::zero();

            $variance = $targetStock->minus($currentStock);

            DB::table('resto_ingredient_stocks')
                ->where('id', $stockRow->id)
                ->update([
                    'stock_base_unit' => $targetStock->toScale(6, RoundingMode::HalfUp)->__toString(),
                    'updated_at' => now(),
                ]);

            DB::table('resto_ingredient_movements')->insert([
                'outlet_id' => $outletId,
                'ingredient_id' => $ingredientId,
                'qty_base_unit' => $variance->toScale(6, RoundingMode::HalfUp)->__toString(),
                'reason' => $reason,
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
