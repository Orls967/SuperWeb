<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Resto\Domain\Enums\POStatus;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\PurchaseOrderLine;
use Modules\Resto\Domain\Models\Supplier;
use Modules\Resto\Domain\Models\UnitConversion;

class CreatePurchaseOrderAction
{
    /**
     * @param  array<int, array{ingredient_id: int, qty: float|string, unit?: string, unit_price: int}>  $linesData
     */
    public function handle(
        int $outletId,
        int $supplierId,
        array $linesData,
        User $creator,
        ?string $expectedAt = null,
        bool $autoSend = false
    ): PurchaseOrder {
        $outlet = Outlet::findOrFail($outletId);
        $supplier = Supplier::findOrFail($supplierId);

        return DB::transaction(function () use ($outlet, $supplier, $linesData, $creator, $expectedAt, $autoSend) {
            $dateStr = date('Ymd');
            $randomCode = strtoupper(Str::random(4));
            $number = "PO-RSR-{$outlet->code}-{$dateStr}-{$randomCode}";

            $po = PurchaseOrder::create([
                'uuid' => (string) Str::uuid(),
                'number' => $number,
                'outlet_id' => $outlet->id,
                'supplier_id' => $supplier->id,
                'status' => $autoSend ? POStatus::SENT : POStatus::DRAFT,
                'expected_at' => $expectedAt ? now()->parse($expectedAt) : now()->addDays(3),
                'subtotal' => 0,
                'ppn' => 0,
                'grand_total' => 0,
                'paid_amount' => 0,
                'created_by' => $creator->id,
            ]);

            $subtotalInt = 0;

            foreach ($linesData as $item) {
                $ing = Ingredient::findOrFail($item['ingredient_id']);
                $qtyOrdered = BigDecimal::of((string) $item['qty']);
                $unit = $item['unit'] ?? $ing->base_unit->value;
                $unitPrice = (int) $item['unit_price'];

                // Calculate base unit factor
                $factor = BigDecimal::one();
                if ($unit !== $ing->base_unit->value) {
                    $conv = UnitConversion::where(function ($q) use ($ing) {
                        $q->where('ingredient_id', $ing->id)->orWhereNull('ingredient_id');
                    })->where('from_unit', $unit)->first();

                    if ($conv) {
                        $factor = BigDecimal::of((string) $conv->to_base_factor);
                    } elseif ($unit === 'kg' && $ing->base_unit->value === 'gram') {
                        $factor = BigDecimal::of('1000');
                    } elseif ($unit === 'liter' && $ing->base_unit->value === 'ml') {
                        $factor = BigDecimal::of('1000');
                    }
                }

                $qtyBaseUnit = $qtyOrdered->multipliedBy($factor)->toScale(6, RoundingMode::HalfUp);
                $lineTotal = $qtyOrdered->multipliedBy(BigDecimal::of($unitPrice))->toScale(0, RoundingMode::HalfUp)->toInt();
                $subtotalInt += $lineTotal;

                PurchaseOrderLine::create([
                    'po_id' => $po->id,
                    'ingredient_id' => $ing->id,
                    'qty_ordered' => $qtyOrdered->__toString(),
                    'unit' => $unit,
                    'qty_base_unit' => $qtyBaseUnit->__toString(),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'qty_received' => '0',
                ]);
            }

            // PPN 11%
            $ppn = (int) round($subtotalInt * 0.11);
            $grandTotal = $subtotalInt + $ppn;

            $po->subtotal = $subtotalInt;
            $po->ppn = $ppn;
            $po->grand_total = $grandTotal;
            $po->save();

            return $po->load(['lines.ingredient', 'supplier', 'outlet']);
        });
    }
}
