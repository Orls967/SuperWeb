<?php

declare(strict_types=1);

namespace Modules\ControlTower\Console\Commands;

use Illuminate\Console\Command;
use Modules\ControlTower\Domain\Algorithms\MultiEchelonSafetyStock;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;

final class CalculateSafetyStockCommand extends Command
{
    protected $signature = 'tower:safety-stock {--item= : Kode SKU tertentu} {--node= : Filter node echelon (PLANT, DC, OUTLET)}';

    protected $description = 'Hitung safety stock multi-echelon ABC-XYZ (I3) berbasis statistik waktu tunggu pemasok';

    public function handle(MultiEchelonSafetyStock $algorithm): int
    {
        $itemOpt = $this->option('item');
        $nodeOpt = $this->option('node');

        $itemFilter = is_string($itemOpt) && trim($itemOpt) !== '' ? trim($itemOpt) : null;
        $nodeFilter = is_string($nodeOpt) && trim($nodeOpt) !== '' ? strtoupper(trim($nodeOpt)) : null;

        $query = EchelonStock::query();
        if ($itemFilter !== null) {
            $query->where('item_code', $itemFilter);
        }
        if ($nodeFilter !== null) {
            $query->where('echelon_node', $nodeFilter);
        }

        $grouped = $query->orderBy('item_code')->get()->groupBy('item_code');

        if ($grouped->isEmpty() && $itemFilter !== null) {
            $grouped = collect([$itemFilter => collect()]);
        }

        $rows = [];
        foreach ($grouped as $itemCode => $stocks) {
            $code = (string) $itemCode;
            $abcClass = (string) ($stocks->first()?->abc_class ?? 'B');
            if (! in_array($abcClass, ['A', 'B', 'C'], true)) {
                $abcClass = 'B';
            }

            $forecasts = DemandForecast::query()
                ->where('item_code', $code)
                ->orderBy('period')
                ->get(['actual_qty', 'forecast_qty']);

            $demandHistory = [];
            foreach ($forecasts as $fc) {
                $demandHistory[] = max(0, (int) ($fc->actual_qty ?? $fc->forecast_qty));
            }
            if ($demandHistory === []) {
                $demandHistory = [max(1, (int) $stocks->sum('on_hand_qty'))];
            }

            $calc = $algorithm->calculateWithSupplierStatistics(
                itemCode: $code,
                abcClass: $abcClass,
                demandHistory: $demandHistory,
            );

            SafetyStockPolicy::updateOrCreate(
                ['item_code' => $code],
                [
                    'abc_class' => $calc['abc_class'],
                    'xyz_class' => $calc['xyz_class'],
                    'service_level_bps' => $calc['service_level_bps'],
                    'safety_stock_qty' => $calc['safety_stock_qty'],
                    'reorder_point_qty' => $calc['reorder_point_qty'],
                    'echelon_breakdown' => $calc['echelon_breakdown'],
                    'supplier_ref' => $calc['supplier_ref'],
                    'lead_time_mean_days' => $calc['lead_time_days'],
                    'lead_time_sigma_days' => $calc['lead_time_sigma_days'],
                    'certificate_digest' => $calc['certificate']->digest,
                    'status' => 'approved',
                ],
            );

            $nodeBreakdown = $nodeFilter !== null
                ? ($calc['echelon_breakdown'][$nodeFilter] ?? null)
                : null;

            $rows[] = [
                $code,
                $calc['abc_class'].$calc['xyz_class'],
                (string) $calc['lead_time_days'],
                (string) $calc['lead_time_sigma_days'],
                (string) ($nodeBreakdown['safety_stock_qty'] ?? $calc['safety_stock_qty']),
                (string) ($nodeBreakdown['reorder_point_qty'] ?? $calc['reorder_point_qty']),
                $nodeFilter ?? 'ALL',
            ];
        }

        if ($rows !== []) {
            $this->table(
                ['SKU', 'Kelas', 'LT Mean (d)', 'LT Sigma (d)', 'Safety Stock', 'ROP', 'Node'],
                $rows,
            );
        }

        $count = count($rows);
        $this->info("Calculated multi-echelon safety stock for {$count} item(s).");

        return self::SUCCESS;
    }
}
