<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class HotelSpaResourceInventoryManager
{
    /**
     * @var array<string, array{name: string, stock_qty: int, min_threshold: int, unit_cost_idr: int}>
     */
    private array $inventory = [
        'LAVENDER_MASSAGE_OIL_ML' => [
            'name' => 'Organic Lavender Massage Oil',
            'stock_qty' => 5000, // ml
            'min_threshold' => 1500,
            'unit_cost_idr' => 200, // per ml
        ],
        'HERBAL_SCRUB_GRAM' => [
            'name' => 'Royal Javanese Herbal Scrub',
            'stock_qty' => 8000, // gram
            'min_threshold' => 2000,
            'unit_cost_idr' => 150, // per gram
        ],
        'VOLCANIC_HOT_STONES_SET' => [
            'name' => 'Basalt Volcanic Massage Stones',
            'stock_qty' => 12, // sets
            'min_threshold' => 4,
            'unit_cost_idr' => 450000,
        ],
    ];

    /**
     * Memotong inventori bahan habis pakai saat sesi treatment diselesaikan.
     *
     * @param  array<string, int>  $deductions  Kode bahan => kuantitas
     * @return array{status: string, total_deduction_cost_idr: int, items_below_threshold: list<string>, processed_at: string}
     */
    public function deductConsumables(array $deductions): array
    {
        $totalCost = 0;
        $alerts = [];

        foreach ($deductions as $sku => $qty) {
            if (! isset($this->inventory[$sku])) {
                throw new InvalidArgumentException("Item spa inventory tidak dikenal: {$sku}");
            }

            if ($qty <= 0) {
                continue;
            }

            $currentStock = $this->inventory[$sku]['stock_qty'];
            if ($qty > $currentStock) {
                throw new InvalidArgumentException("Stok tidak mencukupi untuk {$sku}. Diminta: {$qty}, Tersedia: {$currentStock}");
            }

            $this->inventory[$sku]['stock_qty'] -= $qty;
            $totalCost += ($qty * $this->inventory[$sku]['unit_cost_idr']);

            if ($this->inventory[$sku]['stock_qty'] <= $this->inventory[$sku]['min_threshold']) {
                $alerts[] = $sku;
            }
        }

        return [
            'status' => 'INVENTORY_DEDUCTED',
            'total_deduction_cost_idr' => $totalCost,
            'items_below_threshold' => $alerts,
            'processed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
