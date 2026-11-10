<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Reports;

use Modules\Treasury\Domain\Models\FxLot;

/**
 * Exporter Subledger Lot Valas (PSAK 10 FIFO / Spesifik).
 */
final class FxLotSubledgerReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(?string $currency = null, ?string $status = null): array
    {
        $query = FxLot::query()->orderBy('lot_date');

        if ($currency !== null) {
            $query->where('currency', strtoupper($currency));
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $lots = $query->get();

        $rows = [];
        $rows[] = [
            'Lot ID', 'Entity ID', 'Currency', 'Lot Date', 'Original Amount', 'Remaining Amount',
            'Original Rate', 'Original Cost IDR', 'Carrying Rate', 'Carrying Cost IDR', 'Status', 'Source Ref',
        ];

        $totalOriginalCostIdr = 0;
        $totalCarryingCostIdr = 0;

        foreach ($lots as $lot) {
            $totalOriginalCostIdr += (int) $lot->original_cost_idr;
            $totalCarryingCostIdr += (int) $lot->current_carrying_idr;

            $rows[] = [
                $lot->id,
                $lot->entity_id,
                $lot->currency,
                $lot->lot_date ? $lot->lot_date->format('Y-m-d') : '',
                (string) $lot->original_amount,
                (string) $lot->remaining_amount,
                "{$lot->original_rate_num}/{$lot->original_rate_den}",
                (string) $lot->original_cost_idr,
                "{$lot->current_carrying_rate_num}/{$lot->current_carrying_rate_den}",
                (string) $lot->current_carrying_idr,
                $lot->status,
                $lot->source_ref ?? '',
            ];
        }

        $rows[] = [
            'TOTAL', '', '', '', '', '',
            '', (string) $totalOriginalCostIdr, '', (string) $totalCarryingCostIdr, '', '',
        ];

        $csv = $this->buildCsv($rows);

        $suffix = $currency ? "-{$currency}" : '';

        return [
            'filename' => "treasury-fx-lots{$suffix}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

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
