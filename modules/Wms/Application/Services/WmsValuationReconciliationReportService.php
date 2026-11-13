<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Laporan rekonsiliasi penilaian persediaan seluruh gudang terhadap buku besar.
 */
final class WmsValuationReconciliationReportService
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     warehouse_count: int,
     *     total_subledger_idr: int,
     *     total_ledger_idr: int,
     *     total_discrepancy_idr: int,
     *     is_reconciled: bool,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function generateReport(): array
    {
        $rows = $this->valuationReader->getAllWarehousesValuationIdr();
        $totalSub = 0;
        $totalLed = 0;
        $totalDisc = 0;

        foreach ($rows as $row) {
            $totalSub += (int) $row['subledger_value_idr'];
            $totalLed += (int) $row['ledger_balance_idr'];
            $totalDisc += (int) $row['discrepancy_idr'];
        }

        return [
            'warehouse_count' => count($rows),
            'total_subledger_idr' => $totalSub,
            'total_ledger_idr' => $totalLed,
            'total_discrepancy_idr' => $totalDisc,
            'is_reconciled' => $totalDisc === 0,
            'rows' => $rows,
        ];
    }
}
