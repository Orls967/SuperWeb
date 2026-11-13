<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wms\Contracts\WarehouseValuationReader;

/**
 * Penyusun laporan audit terstruktur WMS (stok negatif, transfer yatim, & rekonsiliasi ledger).
 */
final class WmsAuditReportBuilder
{
    public function __construct(
        private readonly WarehouseValuationReader $valuationReader,
    ) {}

    /**
     * @return array{
     *     negative_bin_count: int,
     *     orphan_transfer_lines: int,
     *     ledger_discrepancies: list<array<string, mixed>>,
     *     passed: bool
     * }
     */
    public function build(): array
    {
        $negativeBins = (int) DB::table('wms_bin_stocks')
            ->where(function ($q): void {
                $q->where('qty', '<', 0)->orWhere('qty_base', '<', 0);
            })
            ->count();

        $orphanLines = (int) DB::table('wms_transfer_lines as tl')
            ->join('wms_transfers as t', 't.id', '=', 'tl.transfer_id')
            ->where('t.status', 'received')
            ->where('tl.status', '!=', 'received')
            ->count();

        $valuations = $this->valuationReader->getAllWarehousesValuationIdr();
        $discrepancies = array_values(array_filter(
            $valuations,
            static fn (array $v): bool => $v['discrepancy_idr'] !== 0
        ));

        return [
            'negative_bin_count' => $negativeBins,
            'orphan_transfer_lines' => $orphanLines,
            'ledger_discrepancies' => $discrepancies,
            'passed' => $negativeBins === 0 && $orphanLines === 0 && $discrepancies === [],
        ];
    }
}
