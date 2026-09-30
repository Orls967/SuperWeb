<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Queries;

use Carbon\Carbon;
use Modules\Resto\Application\Services\RecipeCostCalculator;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\OrderItem;

class MenuEngineeringQuery
{
    public function __construct(
        private readonly RecipeCostCalculator $calculator
    ) {}

    /**
     * @return array{
     *     average_volume: float,
     *     average_margin: float,
     *     items: array<int, array{
     *         item: MenuItem,
     *         volume: int,
     *         revenue: int,
     *         cost_per_portion: int,
     *         margin_per_portion: int,
     *         total_margin: int,
     *         quadrant: 'star'|'plowhorse'|'puzzle'|'dog',
     *         quadrant_label: string
     *     }>,
     *     quadrant_counts: array{star: int, plowhorse: int, puzzle: int, dog: int}
     * }
     */
    public function execute(?int $outletId = null, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        // 1. Ambil seluruh menu item aktif
        $menuItems = MenuItem::where('is_active', true)->with('category')->get();

        // 2. Ambil volume penjualan per menu item dalam 30 hari terakhir
        $salesQuery = OrderItem::whereHas('order', function ($q) use ($outletId, $startDate) {
            $q->where('status', OrderStatus::PAID)
                ->where('created_at', '>=', $startDate);

            if ($outletId) {
                $q->where('outlet_id', $outletId);
            }
        })
            ->selectRaw('menu_item_id, SUM(qty) as total_qty, SUM(line_total) as total_revenue')
            ->groupBy('menu_item_id');

        $salesData = $salesQuery->get()->keyBy('menu_item_id');

        $calculatedItems = [];
        $totalVolumeSum = 0;
        $totalMarginSum = 0;
        $itemCount = 0;

        foreach ($menuItems as $item) {
            $vol = isset($salesData[$item->id]) ? (int) $salesData[$item->id]->total_qty : 0;
            $rev = isset($salesData[$item->id]) ? (int) $salesData[$item->id]->total_revenue : 0;

            // Hitung HPP per porsi
            $calc = $this->calculator->calculateForMenuItem($item, $outletId);
            $costPerPortion = $calc['cost_per_portion_idr'];

            // Jika belum ada resep detail, gunakan estimasi HPP 40% dari harga jual
            if ($costPerPortion <= 0) {
                $costPerPortion = (int) round($item->price * 0.40);
            }

            $margin = $item->price - $costPerPortion;
            $totalMargin = $margin * $vol;

            $totalVolumeSum += $vol;
            $totalMarginSum += $margin;
            $itemCount++;

            $calculatedItems[] = [
                'item' => $item,
                'volume' => $vol,
                'revenue' => $rev,
                'cost_per_portion' => $costPerPortion,
                'margin_per_portion' => $margin,
                'total_margin' => $totalMargin,
            ];
        }

        $avgVolume = $itemCount > 0 ? (float) ($totalVolumeSum / $itemCount) : 0.0;
        $avgMargin = $itemCount > 0 ? (float) ($totalMarginSum / $itemCount) : 0.0;

        $quadrantCounts = ['star' => 0, 'plowhorse' => 0, 'puzzle' => 0, 'dog' => 0];

        $classifiedItems = array_map(function ($row) use ($avgVolume, $avgMargin, &$quadrantCounts) {
            $isHighVolume = $row['volume'] >= $avgVolume;
            $isHighMargin = $row['margin_per_portion'] >= $avgMargin;

            if ($isHighVolume && $isHighMargin) {
                $quadrant = 'star';
                $label = 'Star (Bintang: Volume Tinggi, Margin Tinggi)';
            } elseif ($isHighVolume && ! $isHighMargin) {
                $quadrant = 'plowhorse';
                $label = 'Plowhorse (Kuda Beban: Volume Tinggi, Margin Rendah)';
            } elseif (! $isHighVolume && $isHighMargin) {
                $quadrant = 'puzzle';
                $label = 'Puzzle (Teka-Teki: Margin Tinggi, Volume Rendah)';
            } else {
                $quadrant = 'dog';
                $label = 'Dog (Anjing: Margin Rendah, Volume Rendah)';
            }

            $quadrantCounts[$quadrant]++;

            return array_merge($row, [
                'quadrant' => $quadrant,
                'quadrant_label' => $label,
            ]);
        }, $calculatedItems);

        // Urutkan item berdasarkan total margin descending
        usort($classifiedItems, fn ($a, $b) => $b['total_margin'] <=> $a['total_margin']);

        return [
            'average_volume' => round($avgVolume, 2),
            'average_margin' => round($avgMargin, 0),
            'items' => $classifiedItems,
            'quadrant_counts' => $quadrantCounts,
        ];
    }
}
