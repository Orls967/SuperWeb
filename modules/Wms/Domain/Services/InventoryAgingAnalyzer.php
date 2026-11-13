<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

/**
 * Analisis umur persediaan (inventory aging buckets) dan estimasi cadangan penurunan nilai (bps).
 */
final class InventoryAgingAnalyzer
{
    /**
     * @param  list<array{product_id: int, qty_base: int, value_idr: int, days_in_stock: int}>  $lots
     * @return array{
     *     buckets: array<string, array{qty_base: int, value_idr: int, provision_idr: int}>,
     *     total_value_idr: int,
     *     total_provision_idr: int
     * }
     */
    public function analyze(array $lots): array
    {
        $buckets = [
            '0_30' => ['qty_base' => 0, 'value_idr' => 0, 'provision_idr' => 0, 'bps' => 0],
            '31_60' => ['qty_base' => 0, 'value_idr' => 0, 'provision_idr' => 0, 'bps' => 200],
            '61_90' => ['qty_base' => 0, 'value_idr' => 0, 'provision_idr' => 0, 'bps' => 500],
            '91_180' => ['qty_base' => 0, 'value_idr' => 0, 'provision_idr' => 0, 'bps' => 1500],
            'over_180' => ['qty_base' => 0, 'value_idr' => 0, 'provision_idr' => 0, 'bps' => 5000],
        ];

        $totalValue = 0;
        $totalProvision = 0;

        foreach ($lots as $lot) {
            $days = max(0, (int) $lot['days_in_stock']);
            $qty = max(0, (int) $lot['qty_base']);
            $val = max(0, (int) $lot['value_idr']);

            $key = match (true) {
                $days <= 30 => '0_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                $days <= 180 => '91_180',
                default => 'over_180',
            };

            $prov = intdiv($val * $buckets[$key]['bps'], 10000);
            $buckets[$key]['qty_base'] += $qty;
            $buckets[$key]['value_idr'] += $val;
            $buckets[$key]['provision_idr'] += $prov;

            $totalValue += $val;
            $totalProvision += $prov;
        }

        $cleanBuckets = [];
        foreach ($buckets as $k => $b) {
            $cleanBuckets[$k] = [
                'qty_base' => $b['qty_base'],
                'value_idr' => $b['value_idr'],
                'provision_idr' => $b['provision_idr'],
            ];
        }

        return [
            'buckets' => $cleanBuckets,
            'total_value_idr' => $totalValue,
            'total_provision_idr' => $totalProvision,
        ];
    }
}
