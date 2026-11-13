<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\ControlTower\Application\Actions\CaptureControlTowerKpiSnapshotAction;
use Modules\ControlTower\Domain\Models\ControlTowerKpiSnapshot;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\Core\Contracts\ClockInterface;
use Modules\Distribution\Contracts\OpenDemandProvider;

final class ControlTowerKpiDrilldownService
{
    public const DEFAULT_DSO_DAYS = 20;

    public const DEFAULT_DPO_DAYS = 30;

    public function __construct(
        private readonly OpenDemandProvider $openDemandProvider,
        private readonly CaptureControlTowerKpiSnapshotAction $captureAction,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Menghitung metrik KPI riil (permil integer 0..1000) dan drilldown per SKU & per pesanan.
     *
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     metrics: array{
     *         otif_permil: int,
     *         fill_rate_permil: int,
     *         stockout_items_count: int,
     *         active_alerts_count: int,
     *         inventory_dos_days: int,
     *         cash_to_cash_days: int,
     *         total_lines_count: int,
     *         otif_lines_count: int,
     *         total_ordered_qty: int,
     *         total_delivered_qty: int,
     *         total_capped_delivered_qty: int,
     *         total_on_hand_units: int,
     *         daily_demand_units: int
     *     },
     *     drilldown: array{
     *         per_sku: list<array{
     *             item_code: string,
     *             lines_count: int,
     *             ordered_qty: int,
     *             delivered_qty: int,
     *             capped_delivered_qty: int,
     *             otif_lines_count: int,
     *             otif_permil: int,
     *             fill_rate_permil: int,
     *             status_counts: array{ON_TIME: int, LATE: int, SHORT: int, OPEN: int}
     *         }>,
     *         per_order: list<array{
     *             order_ref: string,
     *             customer_ref: string,
     *             due_date: string,
     *             delivered_date: ?string,
     *             ordered_qty: int,
     *             delivered_qty: int,
     *             otif_permil: int,
     *             fill_rate_permil: int,
     *             status: string,
     *             lines: list<array{
     *                 order_ref: string,
     *                 customer_ref: string,
     *                 item_code: string,
     *                 ordered_qty: int,
     *                 delivered_qty: int,
     *                 due_date: string,
     *                 delivered_date: ?string,
     *                 status: string
     *             }>
     *         }>,
     *         lines: list<array{
     *             order_ref: string,
     *             customer_ref: string,
     *             item_code: string,
     *             ordered_qty: int,
     *             delivered_qty: int,
     *             due_date: string,
     *             delivered_date: ?string,
     *             status: string
     *         }>
     *     }
     * }
     */
    public function drilldown(?string $fromDate = null, ?string $toDate = null): array
    {
        $now = $this->clock->now();
        $to = $toDate !== null && trim($toDate) !== '' ? trim($toDate) : $now->toDateString();
        $from = $fromDate !== null && trim($fromDate) !== '' ? trim($fromDate) : $now->subDays(30)->toDateString();

        if ($from > $to) {
            throw new InvalidArgumentException("INVALID_KPI_PERIOD: Tanggal awal '{$from}' melebihi tanggal akhir '{$to}'.");
        }

        $periodDays = max(1, (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);
        $rawLines = $this->openDemandProvider->fulfillmentLines($from, $to);

        $classifiedLines = [];
        $skuBuckets = [];
        $orderBuckets = [];

        $totalLines = 0;
        $otifLinesCount = 0;
        $sumOrderedQty = 0;
        $sumDeliveredQty = 0;
        $sumCappedDeliveredQty = 0;

        foreach ($rawLines as $line) {
            $orderedQty = max(0, (int) $line['ordered_qty']);
            $deliveredQty = max(0, (int) $line['delivered_qty']);
            $dueDate = (string) $line['due_date'];
            $deliveredDate = $line['delivered_date'];
            $status = $this->classifyLineStatus($orderedQty, $deliveredQty, $dueDate, $deliveredDate);

            $isOtif = $status === 'ON_TIME';
            $cappedDelivered = min($deliveredQty, $orderedQty);

            $classified = [
                'order_ref' => (string) $line['order_ref'],
                'customer_ref' => (string) $line['customer_ref'],
                'item_code' => (string) $line['item_code'],
                'ordered_qty' => $orderedQty,
                'delivered_qty' => $deliveredQty,
                'due_date' => $dueDate,
                'delivered_date' => $deliveredDate,
                'status' => $status,
            ];
            $classifiedLines[] = $classified;

            $totalLines++;
            if ($isOtif) {
                $otifLinesCount++;
            }
            $sumOrderedQty += $orderedQty;
            $sumDeliveredQty += $deliveredQty;
            $sumCappedDeliveredQty += $cappedDelivered;

            $sku = $classified['item_code'];
            if (! isset($skuBuckets[$sku])) {
                $skuBuckets[$sku] = [
                    'item_code' => $sku,
                    'lines_count' => 0,
                    'ordered_qty' => 0,
                    'delivered_qty' => 0,
                    'capped_delivered_qty' => 0,
                    'otif_lines_count' => 0,
                    'status_counts' => ['ON_TIME' => 0, 'LATE' => 0, 'SHORT' => 0, 'OPEN' => 0],
                ];
            }
            $skuBuckets[$sku]['lines_count']++;
            $skuBuckets[$sku]['ordered_qty'] += $orderedQty;
            $skuBuckets[$sku]['delivered_qty'] += $deliveredQty;
            $skuBuckets[$sku]['capped_delivered_qty'] += $cappedDelivered;
            if ($isOtif) {
                $skuBuckets[$sku]['otif_lines_count']++;
            }
            $skuBuckets[$sku]['status_counts'][$status]++;

            $orderRef = $classified['order_ref'];
            if (! isset($orderBuckets[$orderRef])) {
                $orderBuckets[$orderRef] = [
                    'order_ref' => $orderRef,
                    'customer_ref' => $classified['customer_ref'],
                    'due_date' => $dueDate,
                    'delivered_date' => $deliveredDate,
                    'ordered_qty' => 0,
                    'delivered_qty' => 0,
                    'capped_delivered_qty' => 0,
                    'otif_lines_count' => 0,
                    'lines' => [],
                ];
            }
            $orderBuckets[$orderRef]['ordered_qty'] += $orderedQty;
            $orderBuckets[$orderRef]['delivered_qty'] += $deliveredQty;
            $orderBuckets[$orderRef]['capped_delivered_qty'] += $cappedDelivered;
            if ($isOtif) {
                $orderBuckets[$orderRef]['otif_lines_count']++;
            }
            $orderBuckets[$orderRef]['lines'][] = $classified;
        }

        $otifPermil = $this->permil($otifLinesCount, $totalLines);
        $fillRatePermil = $this->permil($sumCappedDeliveredQty, $sumOrderedQty);

        $perSku = [];
        ksort($skuBuckets);
        foreach ($skuBuckets as $bucket) {
            $perSku[] = [
                'item_code' => $bucket['item_code'],
                'lines_count' => $bucket['lines_count'],
                'ordered_qty' => $bucket['ordered_qty'],
                'delivered_qty' => $bucket['delivered_qty'],
                'capped_delivered_qty' => $bucket['capped_delivered_qty'],
                'otif_lines_count' => $bucket['otif_lines_count'],
                'otif_permil' => $this->permil($bucket['otif_lines_count'], $bucket['lines_count']),
                'fill_rate_permil' => $this->permil($bucket['capped_delivered_qty'], $bucket['ordered_qty']),
                'status_counts' => $bucket['status_counts'],
            ];
        }

        $perOrder = [];
        foreach ($orderBuckets as $bucket) {
            $lineCount = count($bucket['lines']);
            $perOrder[] = [
                'order_ref' => $bucket['order_ref'],
                'customer_ref' => $bucket['customer_ref'],
                'due_date' => $bucket['due_date'],
                'delivered_date' => $bucket['delivered_date'],
                'ordered_qty' => $bucket['ordered_qty'],
                'delivered_qty' => $bucket['delivered_qty'],
                'otif_permil' => $this->permil($bucket['otif_lines_count'], $lineCount),
                'fill_rate_permil' => $this->permil($bucket['capped_delivered_qty'], $bucket['ordered_qty']),
                'status' => $this->aggregateOrderStatus($bucket['lines']),
                'lines' => $bucket['lines'],
            ];
        }

        $stockoutItemsCount = $this->countStockoutItems();
        $activeAlertsCount = DisruptionAlert::query()->where('status', 'active')->count();
        $totalOnHandUnits = max(0, (int) EchelonStock::query()->sum('on_hand_qty'));

        $dailyDemandUnits = $this->resolveDailyDemandUnits($sumOrderedQty, $periodDays);
        $inventoryDosDays = $dailyDemandUnits > 0 ? intdiv($totalOnHandUnits, $dailyDemandUnits) : 0;
        $cashToCashDays = $inventoryDosDays + self::DEFAULT_DSO_DAYS - self::DEFAULT_DPO_DAYS;

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
                'days' => $periodDays,
            ],
            'metrics' => [
                'otif_permil' => $otifPermil,
                'fill_rate_permil' => $fillRatePermil,
                'stockout_items_count' => $stockoutItemsCount,
                'active_alerts_count' => $activeAlertsCount,
                'inventory_dos_days' => $inventoryDosDays,
                'cash_to_cash_days' => $cashToCashDays,
                'total_lines_count' => $totalLines,
                'otif_lines_count' => $otifLinesCount,
                'total_ordered_qty' => $sumOrderedQty,
                'total_delivered_qty' => $sumDeliveredQty,
                'total_capped_delivered_qty' => $sumCappedDeliveredQty,
                'total_on_hand_units' => $totalOnHandUnits,
                'daily_demand_units' => $dailyDemandUnits,
            ],
            'drilldown' => [
                'per_sku' => $perSku,
                'per_order' => $perOrder,
                'lines' => $classifiedLines,
            ],
        ];
    }

    /**
     * Menghitung KPI riil dan menyimpan snapshot harian secara idempoten berbasis snapshot_date.
     *
     * @return array{
     *     snapshot: ControlTowerKpiSnapshot,
     *     period: array{from: string, to: string, days: int},
     *     metrics: array<string, int>,
     *     drilldown: array<string, mixed>
     * }
     */
    public function captureDailySnapshot(?string $fromDate = null, ?string $toDate = null): array
    {
        $report = $this->drilldown($fromDate, $toDate);
        $metrics = $report['metrics'];
        $totalLines = $metrics['total_lines_count'];

        $snapshot = $this->captureAction->execute(
            onTimeInFullOrders: $totalLines > 0 ? $metrics['otif_lines_count'] : 0,
            totalDeliveredOrders: $totalLines > 0 ? $totalLines : 1,
            dioDays: $metrics['inventory_dos_days'],
            dsoDays: self::DEFAULT_DSO_DAYS,
            dpoDays: self::DEFAULT_DPO_DAYS,
            endingInventoryUnits: $metrics['total_on_hand_units'],
            dailyDemandUnits: $metrics['daily_demand_units'],
            totalLeadTimeHours: ($totalLines > 0 ? $totalLines : 1) * 24,
            logisticsCostIdr: 40_000_000,
            netSalesIdr: 1_000_000_000,
        );

        return $report + ['snapshot' => $snapshot];
    }

    private function classifyLineStatus(
        int $orderedQty,
        int $deliveredQty,
        string $dueDate,
        ?string $deliveredDate,
    ): string {
        if ($deliveredDate === null && $deliveredQty <= 0) {
            return 'OPEN';
        }

        if ($deliveredQty < $orderedQty) {
            return 'SHORT';
        }

        if ($deliveredDate === null || $deliveredDate > $dueDate) {
            return 'LATE';
        }

        return 'ON_TIME';
    }

    /**
     * @param  list<array{status: string}>  $lines
     */
    private function aggregateOrderStatus(array $lines): string
    {
        $statuses = array_column($lines, 'status');
        foreach (['OPEN', 'SHORT', 'LATE'] as $priority) {
            if (in_array($priority, $statuses, true)) {
                return $priority;
            }
        }

        return 'ON_TIME';
    }

    private function permil(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator <= 0) {
            return 0;
        }

        return max(0, min(1000, intdiv($numerator * 1000, $denominator)));
    }

    private function countStockoutItems(): int
    {
        $stocks = EchelonStock::query()
            ->get(['item_code', 'on_hand_qty', 'reserved_qty']);

        if ($stocks->isEmpty()) {
            return 0;
        }

        $netBySku = [];
        foreach ($stocks as $stock) {
            $code = (string) $stock->item_code;
            $net = (int) $stock->on_hand_qty - (int) $stock->reserved_qty;
            $netBySku[$code] = ($netBySku[$code] ?? 0) + $net;
        }

        $stockouts = 0;
        foreach ($netBySku as $available) {
            if ($available <= 0) {
                $stockouts++;
            }
        }

        return $stockouts;
    }

    private function resolveDailyDemandUnits(int $sumOrderedQty, int $periodDays): int
    {
        if ($sumOrderedQty > 0) {
            return max(1, intdiv($sumOrderedQty, $periodDays));
        }

        $avgMonthlyForecast = (int) round((float) (DemandForecast::query()->avg('forecast_qty') ?? 0));

        return $avgMonthlyForecast > 0 ? max(1, intdiv($avgMonthlyForecast, 30)) : 0;
    }
}
