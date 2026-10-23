<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Modules\Shared\Application\BaseAction;

class MenuEngineeringMatrixAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        /** @var array<int, array{id: int|string, name: string, sales_volume: int, selling_price_idr: int, cogs_idr: int}> $items */
        $items = $params['items'] ?? [];

        if (empty($items)) {
            return [
                'total_items' => 0,
                'total_units_sold' => 0,
                'total_revenue_idr' => 0,
                'total_margin_idr' => 0,
                'average_volume' => 0,
                'average_margin_idr' => 0,
                'quadrants' => [
                    'stars' => [],
                    'plowhorses' => [],
                    'puzzles' => [],
                    'dogs' => [],
                ],
            ];
        }

        $totalUnits = 0;
        $totalRevenue = 0;
        $totalMargin = 0;

        $analyzed = [];
        foreach ($items as $item) {
            $volume = (int) $item['sales_volume'];
            $price = (int) $item['selling_price_idr'];
            $cogs = (int) $item['cogs_idr'];
            $unitMargin = $price - $cogs;
            $itemTotalMargin = $unitMargin * $volume;
            $itemTotalRevenue = $price * $volume;

            $totalUnits += $volume;
            $totalRevenue += $itemTotalRevenue;
            $totalMargin += $itemTotalMargin;

            $analyzed[] = [
                'id' => $item['id'],
                'name' => (string) $item['name'],
                'sales_volume' => $volume,
                'selling_price_idr' => $price,
                'cogs_idr' => $cogs,
                'unit_margin_idr' => $unitMargin,
                'total_margin_idr' => $itemTotalMargin,
                'total_revenue_idr' => $itemTotalRevenue,
            ];
        }

        $count = count($analyzed);
        $avgVolume = (int) round($totalUnits / $count);
        $avgMargin = (int) round($totalMargin / max(1, $totalUnits));

        $stars = [];
        $plowhorses = [];
        $puzzles = [];
        $dogs = [];

        foreach ($analyzed as $row) {
            $isHighVolume = $row['sales_volume'] >= $avgVolume;
            $isHighMargin = $row['unit_margin_idr'] >= $avgMargin;

            if ($isHighVolume && $isHighMargin) {
                $category = 'star';
                $stars[] = $row;
            } elseif ($isHighVolume && ! $isHighMargin) {
                $category = 'plowhorse';
                $plowhorses[] = $row;
            } elseif (! $isHighVolume && $isHighMargin) {
                $category = 'puzzle';
                $puzzles[] = $row;
            } else {
                $category = 'dog';
                $dogs[] = $row;
            }
        }

        $result = [
            'total_items' => $count,
            'total_units_sold' => $totalUnits,
            'total_revenue_idr' => $totalRevenue,
            'total_margin_idr' => $totalMargin,
            'average_volume' => $avgVolume,
            'average_margin_idr' => $avgMargin,
            'quadrants' => [
                'stars' => $stars,
                'plowhorses' => $plowhorses,
                'puzzles' => $puzzles,
                'dogs' => $dogs,
            ],
        ];

        $this->audit(
            action: 'menu_engineering_analyzed',
            newValues: ['total_items' => $count, 'total_revenue_idr' => $totalRevenue],
            impactType: 'resto_analytics',
        );

        return $result;
    }
}
