<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\IntercompanyAssetTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyStockTransfer;

/**
 * Exporter Laporan Transfer Aset Tetap & Persediaan Antar-Entitas (52.3).
 * Memuat rincian perpindahan aset berwujud dan stok antar entitas legal grup,
 * nilai buku historis vs harga transfer arm's length, dan laba persediaan yang belum terealisasi.
 */
final class AssetAndStockTransfersReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $assetTransfers = IntercompanyAssetTransfer::all();
        $stockTransfers = IntercompanyStockTransfer::all();

        $rows = [];
        $rows[] = [
            'Period',
            'Category',
            'Transfer Code',
            'Transferor Entity',
            'Transferee Entity',
            'Item Description',
            'Historical Book Value / Cost IDR',
            'Transfer Price IDR',
            'Gain / Markup IDR',
            'Status',
            'Unrealized Reserve IDR',
            'Ledger Tx Ref',
        ];

        $totalAssetPrice = 0;
        $totalStockPrice = 0;
        $totalGainIdr = 0;

        foreach ($assetTransfers as $at) {
            $gain = (int) ($at->transfer_price_idr - $at->book_value_idr);
            $totalAssetPrice += (int) $at->transfer_price_idr;
            $totalGainIdr += $gain;

            $rows[] = [
                $period,
                'FIXED_ASSET',
                $at->transfer_code,
                $at->transferor_entity,
                $at->transferee_entity,
                $at->asset_name ?? 'Mesin / Peralatan Pabrik',
                (string) $at->book_value_idr,
                (string) $at->transfer_price_idr,
                (string) $gain,
                $at->status,
                '0',
                (string) ($at->ledger_transaction_id ?? '-'),
            ];
        }

        foreach ($stockTransfers as $st) {
            $markup = (int) ($st->transfer_price_idr - $st->cost_price_idr);
            $totalStockPrice += (int) $st->transfer_price_idr;
            $totalGainIdr += $markup;

            $rows[] = [
                $period,
                'INVENTORY_STOCK',
                $st->transfer_code,
                $st->source_entity,
                $st->destination_entity,
                $st->item_sku.' (Qty: '.$st->quantity.')',
                (string) $st->cost_price_idr,
                (string) $st->transfer_price_idr,
                (string) $markup,
                $st->status,
                (string) $markup,
                (string) ($st->ledger_transaction_id ?? '-'),
            ];
        }

        $rows[] = [
            'SUMMARY',
            "Assets: {$assetTransfers->count()} | Stocks: {$stockTransfers->count()}",
            '',
            '',
            '',
            '',
            '',
            (string) ($totalAssetPrice + $totalStockPrice),
            (string) $totalGainIdr,
            'RECORDED',
            '',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "asset-and-stock-transfers-{$period}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content ?: '';
    }
}
