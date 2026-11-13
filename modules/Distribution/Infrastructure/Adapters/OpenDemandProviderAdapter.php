<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Adapters;

use Carbon\Carbon;
use Modules\Distribution\Contracts\OpenDemandProvider;
use Modules\Distribution\Domain\Models\DistOrderLine;

final class OpenDemandProviderAdapter implements OpenDemandProvider
{
    /**
     * @return list<int>
     */
    public function commitmentsByPeriod(string $itemCode, int $periods, string $asOfDate): array
    {
        if ($periods <= 0) {
            return [];
        }

        $buckets = array_fill(0, $periods, 0);
        $baseDate = Carbon::parse($asOfDate)->startOfDay();

        $openLines = DistOrderLine::query()
            ->with('order')
            ->where('sku', $itemCode)
            ->whereNotIn('line_status', ['delivered', 'cancelled'])
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', ['delivered', 'cancelled']))
            ->get();

        foreach ($openLines as $line) {
            $openQty = max(0, (int) round((float) $line->qty - (float) $line->shipped_qty));
            if ($openQty === 0) {
                continue;
            }

            $dueDate = $line->order?->requested_date
                ? Carbon::parse($line->order->requested_date)->startOfDay()
                : $baseDate;
            $diffDays = (int) $baseDate->diffInDays($dueDate, false);
            $index = $diffDays <= 0 ? 0 : min($periods - 1, $diffDays - 1);
            $buckets[$index] += $openQty;
        }

        return $buckets;
    }

    /**
     * @return list<array{order_ref:string, customer_ref:string, item_code:string, ordered_qty:int, delivered_qty:int, due_date:string, delivered_date:?string}>
     */
    public function fulfillmentLines(string $fromDate, string $toDate): array
    {
        $lines = DistOrderLine::query()
            ->with(['order.distributor'])
            ->where('line_status', '!=', 'cancelled')
            ->whereHas('order', function ($query) use ($fromDate, $toDate): void {
                $query->where('status', '!=', 'cancelled')
                    ->where(function ($dateQuery) use ($fromDate, $toDate): void {
                        $dateQuery->whereBetween('requested_date', [$fromDate, $toDate])
                            ->orWhere(function ($fallback) use ($fromDate, $toDate): void {
                                $fallback->whereNull('requested_date')
                                    ->whereBetween('created_at', ["{$fromDate} 00:00:00", "{$toDate} 23:59:59"]);
                            });
                    });
            })
            ->get();

        $rows = [];
        foreach ($lines as $line) {
            $order = $line->order;
            if ($order === null) {
                continue;
            }

            $dueDate = $order->requested_date?->toDateString()
                ?? $order->created_at?->toDateString()
                ?? $fromDate;
            $deliveredDate = $order->delivered_at?->toDateString();
            $isDelivered = $order->status === 'delivered'
                || $line->line_status === 'delivered'
                || $deliveredDate !== null;

            $rows[] = [
                'order_ref' => (string) $order->number,
                'customer_ref' => (string) ($order->distributor?->code ?? $order->distributor_id),
                'item_code' => (string) $line->sku,
                'ordered_qty' => max(0, (int) round((float) $line->qty)),
                'delivered_qty' => $isDelivered ? max(0, (int) round((float) $line->shipped_qty)) : 0,
                'due_date' => $dueDate,
                'delivered_date' => $deliveredDate,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['due_date'], $a['order_ref'], $a['item_code']]
                <=> [$b['due_date'], $b['order_ref'], $b['item_code']];
        });

        return $rows;
    }
}
