<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Queries;

use Carbon\Carbon;
use Modules\Resto\Domain\Enums\POStatus;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\Supplier;

class PayableAgingQuery
{
    /**
     * @return array{
     *     bracket_0_30: int,
     *     bracket_31_60: int,
     *     bracket_60_plus: int,
     *     total: int,
     *     by_supplier: array<int, array{supplier: Supplier, bracket_0_30: int, bracket_31_60: int, bracket_60_plus: int, total: int}>
     * }
     */
    public function execute(?int $outletId = null): array
    {
        return $this->get($outletId);
    }

    public function get(?int $outletId = null): array
    {
        $query = PurchaseOrder::with('supplier')
            ->whereIn('status', [POStatus::SENT, POStatus::PARTIALLY_RECEIVED, POStatus::RECEIVED]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $pos = $query->get();

        $total0To30 = 0;
        $total31To60 = 0;
        $total60Plus = 0;
        $bySupplier = [];

        $now = Carbon::now();

        foreach ($pos as $po) {
            $unpaid = $po->remainingPayable();
            if ($unpaid <= 0) {
                continue;
            }

            $dueDate = $po->expected_at ? Carbon::parse($po->expected_at)->addDays($po->supplier->terms_days) : $po->created_at->addDays($po->supplier->terms_days);
            $ageDays = $now->diffInDays($dueDate, false); // if positive = not overdue yet, if negative = overdue days
            $overdueDays = max(0, (int) -$ageDays);

            $bracket = '0_30';
            if ($overdueDays > 60) {
                $bracket = '60_plus';
                $total60Plus += $unpaid;
            } elseif ($overdueDays > 30) {
                $bracket = '31_60';
                $total31To60 += $unpaid;
            } else {
                $total0To30 += $unpaid;
            }

            $supId = $po->supplier_id;
            if (! isset($bySupplier[$supId])) {
                $bySupplier[$supId] = [
                    'supplier' => $po->supplier,
                    'bracket_0_30' => 0,
                    'bracket_31_60' => 0,
                    'bracket_60_plus' => 0,
                    'total' => 0,
                ];
            }

            $bySupplier[$supId]["bracket_{$bracket}"] += $unpaid;
            $bySupplier[$supId]['total'] += $unpaid;
        }

        return [
            'bracket_0_30' => $total0To30,
            'bracket_31_60' => $total31To60,
            'bracket_60_plus' => $total60Plus,
            'total' => $total0To30 + $total31To60 + $total60Plus,
            'by_supplier' => array_values($bySupplier),
        ];
    }
}
