<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\InventoryHealthSnapshot;
use Modules\ControlTower\Domain\Services\InventoryHealthAnalyzer;
use Modules\Core\Contracts\ClockInterface;

/**
 * Orkestrasi analisis kesehatan persediaan multi-echelon dan persistensi idempoten per item + node + tanggal.
 */
final class InventoryHealthOrchestratorService
{
    public function __construct(
        private readonly InventoryHealthAnalyzer $analyzer,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Analisis seluruh EchelonStock dan simpan snapshot idempoten per (item_code, echelon_node, snapshot_date).
     *
     * @param  array<string, int>  $demandByItem
     * @param  array<string, int>  $unitCostByItem
     * @return Collection<int, InventoryHealthSnapshot>
     */
    public function analyzeAll(
        ?string $snapshotDate = null,
        int $defaultDailyDemandQty = 10,
        int $defaultUnitCostIdr = 100_000,
        array $demandByItem = [],
        array $unitCostByItem = [],
    ): Collection {
        $effectiveDate = ($snapshotDate !== null && trim($snapshotDate) !== '')
            ? trim($snapshotDate)
            : $this->clock->now()->toDateString();

        $stocks = EchelonStock::query()
            ->orderBy('item_code')
            ->orderBy('echelon_node')
            ->get();

        $forecastMonthlyByItem = DemandForecast::query()
            ->orderByDesc('id')
            ->get(['item_code', 'forecast_qty'])
            ->unique('item_code')
            ->mapWithKeys(static fn (DemandForecast $fc): array => [
                $fc->item_code => max(1, intdiv((int) $fc->forecast_qty, 30)),
            ])
            ->all();

        $persisted = new Collection;

        foreach ($stocks as $stock) {
            $itemCode = (string) $stock->item_code;
            $dailyDemand = $demandByItem[$itemCode]
                ?? $forecastMonthlyByItem[$itemCode]
                ?? max(0, $defaultDailyDemandQty);
            $unitCostIdr = $unitCostByItem[$itemCode] ?? max(0, $defaultUnitCostIdr);

            $result = $this->analyzer->analyze(
                stock: $stock,
                dailyDemandQty: (int) $dailyDemand,
                unitCostIdr: (int) $unitCostIdr,
                asOfDate: $effectiveDate,
            );

            $attributes = [
                'health_status' => $result['health_status'],
                'aging_bucket' => $result['aging_bucket'],
                'on_hand_qty' => $result['on_hand_qty'],
                'quarantine_qty' => $result['quarantine_qty'],
                'available_qty' => $result['available_qty'],
                'daily_demand_qty' => $result['daily_demand_qty'],
                'dos_days' => $result['dos_days'],
                'oldest_lot_age_days' => $result['oldest_lot_age_days'],
                'tied_capital_idr' => $result['tied_capital_idr'],
                'obsolescence_risk_idr' => $result['obsolescence_risk_idr'],
            ];

            $snapshot = InventoryHealthSnapshot::query()
                ->where('item_code', $result['item_code'])
                ->where('echelon_node', $result['echelon_node'])
                ->whereDate('snapshot_date', $result['snapshot_date'])
                ->first();

            if ($snapshot !== null) {
                $snapshot->update($attributes);
            } else {
                $snapshot = InventoryHealthSnapshot::query()->create([
                    'item_code' => $result['item_code'],
                    'echelon_node' => $result['echelon_node'],
                    'snapshot_date' => $result['snapshot_date'],
                    ...$attributes,
                ]);
            }

            $persisted->push($snapshot);
        }

        return $persisted;
    }
}
