<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Models\Invoice;

class AgingReceivableQuery
{
    /**
     * Hitung umur piutang sewa & tagihan mall (Aging Receivable) per kelompok hari.
     *
     * @return array{
     *     summary: array{
     *         current: int,
     *         days_1_30: int,
     *         days_31_60: int,
     *         days_61_90: int,
     *         days_over_90: int,
     *         total_outstanding: int
     *     },
     *     counts: array{
     *         current: int,
     *         days_1_30: int,
     *         days_31_60: int,
     *         days_61_90: int,
     *         days_over_90: int
     *     },
     *     invoices: Collection
     * }
     */
    public function execute(?int $propertyId = null): array
    {
        $query = Invoice::with(['tenant', 'lease.unit', 'property'])
            ->whereIn('status', [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE]);

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        $invoices = $query->orderBy('due_date', 'asc')->get();
        $now = Carbon::now()->startOfDay();

        $summary = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
            'total_outstanding' => 0,
        ];

        $counts = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
        ];

        foreach ($invoices as $inv) {
            $outstanding = $inv->remainingAmount();
            $dueDate = Carbon::parse($inv->due_date)->startOfDay();

            if ($now->lessThanOrEqualTo($dueDate)) {
                $bucket = 'current';
            } else {
                $days = abs((int) $now->diffInDays($dueDate));
                if ($days <= 30) {
                    $bucket = 'days_1_30';
                } elseif ($days <= 60) {
                    $bucket = 'days_31_60';
                } elseif ($days <= 90) {
                    $bucket = 'days_61_90';
                } else {
                    $bucket = 'days_over_90';
                }
            }

            $summary[$bucket] += $outstanding;
            $counts[$bucket]++;
            $summary['total_outstanding'] += $outstanding;

            // Tambahkan atribut aging ke objek invoice
            $inv->aging_bucket = $bucket;
            $inv->days_overdue = $now->greaterThan($dueDate) ? abs((int) $now->diffInDays($dueDate)) : 0;
        }

        return [
            'summary' => $summary,
            'counts' => $counts,
            'invoices' => $invoices,
        ];
    }
}
