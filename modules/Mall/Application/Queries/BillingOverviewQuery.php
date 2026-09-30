<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Illuminate\Support\Collection;
use Modules\Mall\Domain\Models\Invoice;

class BillingOverviewQuery
{
    /**
     * Ringkasan performa penagihan bulanan mall.
     *
     * @return array{
     *     period: string,
     *     total_billed: int,
     *     total_collected: int,
     *     total_outstanding: int,
     *     collection_rate: float,
     *     total_penalties: int,
     *     status_counts: array<string, int>,
     *     recent_invoices: Collection
     * }
     */
    public function execute(?string $periodMonth = null, ?int $propertyId = null): array
    {
        $period = $periodMonth ?? date('Y-m');

        $query = Invoice::with(['tenant', 'lease.unit', 'property'])
            ->where('period_month', $period);

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        $invoices = $query->orderBy('id', 'desc')->get();

        $totalBilled = 0;
        $totalCollected = 0;
        $totalOutstanding = 0;
        $totalPenalties = 0;

        $statusCounts = [
            'draft' => 0,
            'issued' => 0,
            'partially_paid' => 0,
            'paid' => 0,
            'overdue' => 0,
            'cancelled' => 0,
        ];

        foreach ($invoices as $inv) {
            $totalBilled += $inv->total_amount;
            $totalCollected += $inv->paid_amount;
            $totalOutstanding += $inv->remainingAmount();
            $totalPenalties += $inv->penalty_amount;

            $statusKey = $inv->status->value;
            $statusCounts[$statusKey] = ($statusCounts[$statusKey] ?? 0) + 1;
        }

        $collectionRate = $totalBilled > 0
            ? round(($totalCollected / $totalBilled) * 100, 2)
            : 0.0;

        return [
            'period' => $period,
            'total_billed' => $totalBilled,
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'collection_rate' => $collectionRate,
            'total_penalties' => $totalPenalties,
            'status_counts' => $statusCounts,
            'recent_invoices' => $invoices,
        ];
    }
}
