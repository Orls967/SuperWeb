<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Adapters;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Distribution\Contracts\OpenDemandProvider;

/**
 * Adaptor komitmen permintaan terbuka dan riwayat pemenuhan pesanan dari tabel dist_*.
 */
final class DistributionOpenDemandProvider implements OpenDemandProvider
{
    private const int BUCKET_DAYS = 7;

    public function commitmentsByPeriod(string $itemCode, int $periods, string $asOfDate): array
    {
        if ($periods <= 0) {
            return [];
        }

        $commitments = array_fill(0, $periods, 0);
        $baseDate = CarbonImmutable::parse($asOfDate)->startOfDay();
        $bucketStarts = $this->bucketStarts($baseDate, $periods);
        $horizonEnd = $baseDate->addDays($periods * self::BUCKET_DAYS)->toDateString();

        $rows = DB::table('dist_order_lines as dol')
            ->join('dist_orders as do', 'do.id', '=', 'dol.order_id')
            ->where('dol.sku', $itemCode)
            ->whereNotIn('do.status', ['cancelled', 'delivered'])
            ->whereNotIn('dol.line_status', ['cancelled', 'delivered', 'shipped'])
            ->select('dol.qty', 'dol.shipped_qty', 'do.requested_date', 'do.created_at')
            ->get();

        foreach ($rows as $row) {
            $dueRaw = $row->requested_date ?? $row->created_at ?? $asOfDate;
            $dueDate = CarbonImmutable::parse((string) $dueRaw)->toDateString();
            if ($dueDate >= $horizonEnd) {
                continue;
            }

            $ordered = BigDecimal::of((string) ($row->qty ?? '0'));
            $shipped = BigDecimal::of((string) ($row->shipped_qty ?? '0'));
            $openQty = max(0, $ordered->minus($shipped)->toScale(0, RoundingMode::Down)->toInt());
            if ($openQty === 0) {
                continue;
            }

            $idx = $this->bucketIndex($dueDate, $bucketStarts);
            $commitments[$idx] += $openQty;
        }

        return $commitments;
    }

    public function fulfillmentLines(string $fromDate, string $toDate): array
    {
        $from = CarbonImmutable::parse($fromDate)->toDateString();
        $to = CarbonImmutable::parse($toDate)->toDateString();

        $rows = DB::table('dist_order_lines as dol')
            ->join('dist_orders as do', 'do.id', '=', 'dol.order_id')
            ->join('dist_distributors as dd', 'dd.id', '=', 'do.distributor_id')
            ->whereNotIn('do.status', ['cancelled'])
            ->whereNotIn('dol.line_status', ['cancelled'])
            ->select(
                'do.id as order_id',
                'do.number as order_ref',
                'do.status as order_status',
                'do.requested_date',
                'do.delivered_at',
                'do.created_at',
                'dd.code as customer_ref',
                'dol.sku as item_code',
                'dol.qty as ordered_qty',
                'dol.shipped_qty as shipped_qty',
                'dol.line_status',
            )
            ->get();

        $orderIds = $rows->pluck('order_id')->unique()->values()->all();
        $podByOrder = [];
        if ($orderIds !== []) {
            $shipments = DB::table('dist_shipments')
                ->whereIn('order_id', $orderIds)
                ->where('status', '!=', 'cancelled')
                ->whereNotNull('pod_at')
                ->select('order_id', 'pod_at')
                ->orderBy('pod_at')
                ->get();

            foreach ($shipments as $shipment) {
                $podByOrder[(string) $shipment->order_id] = CarbonImmutable::parse((string) $shipment->pod_at)->toDateString();
            }
        }

        $lines = [];
        foreach ($rows as $row) {
            $dueRaw = $row->requested_date ?? $row->created_at;
            if ($dueRaw === null) {
                continue;
            }
            $dueDate = CarbonImmutable::parse((string) $dueRaw)->toDateString();
            if ($dueDate < $from || $dueDate > $to) {
                continue;
            }

            $orderedQty = max(0, BigDecimal::of((string) ($row->ordered_qty ?? '0'))->toScale(0, RoundingMode::Down)->toInt());
            $shippedQty = max(0, BigDecimal::of((string) ($row->shipped_qty ?? '0'))->toScale(0, RoundingMode::Down)->toInt());
            $deliveredQty = ($shippedQty === 0 && ($row->order_status === 'delivered' || $row->line_status === 'delivered'))
                ? $orderedQty
                : $shippedQty;

            $orderId = (string) $row->order_id;
            $deliveredDate = $podByOrder[$orderId]
                ?? ($row->delivered_at !== null ? CarbonImmutable::parse((string) $row->delivered_at)->toDateString() : null);

            $lines[] = [
                'order_ref' => (string) $row->order_ref,
                'customer_ref' => (string) $row->customer_ref,
                'item_code' => (string) $row->item_code,
                'ordered_qty' => $orderedQty,
                'delivered_qty' => $deliveredQty,
                'due_date' => $dueDate,
                'delivered_date' => $deliveredDate,
            ];
        }

        usort($lines, static function (array $a, array $b): int {
            $dateCmp = $a['due_date'] <=> $b['due_date'];
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return $a['order_ref'] <=> $b['order_ref'];
        });

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function bucketStarts(CarbonImmutable $baseDate, int $periods): array
    {
        $starts = [];
        for ($i = 0; $i < $periods; $i++) {
            $starts[] = $baseDate->addDays($i * self::BUCKET_DAYS)->toDateString();
        }

        return $starts;
    }

    /**
     * @param  list<string>  $bucketStarts
     */
    private function bucketIndex(string $date, array $bucketStarts): int
    {
        $found = 0;
        foreach ($bucketStarts as $idx => $start) {
            if ($date >= $start) {
                $found = $idx;
            }
        }

        return $found;
    }
}
