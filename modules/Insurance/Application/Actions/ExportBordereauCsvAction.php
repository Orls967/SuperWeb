<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Modules\Insurance\Domain\Models\ReinsuranceBordereau;

final class ExportBordereauCsvAction
{
    public function execute(ReinsuranceBordereau $bordereau): string
    {
        $rows = [];
        $rows[] = ['Bordereau Number', 'Treaty Code', 'Reinsurer ID', 'Kind', 'Period YM', 'Status', 'Ceded IDR', 'Commission IDR', 'Reinstatement IDR'];
        $rows[] = [
            $bordereau->number,
            $bordereau->treaty?->treaty_code ?? '',
            (string) $bordereau->reinsurer_party_id,
            $bordereau->kind,
            $bordereau->period_ym,
            $bordereau->status->value,
            (string) $bordereau->total_ceded_idr,
            (string) $bordereau->total_commission_idr,
            (string) $bordereau->total_reinstatement_idr,
        ];

        $rows[] = [];
        $rows[] = ['Line ID', 'Cession Line ID', 'Amount IDR', 'Commission IDR', 'Reinstatement IDR'];

        foreach ($bordereau->lines as $line) {
            $rows[] = [
                (string) $line->id,
                (string) $line->cession_line_id,
                (string) $line->amount_idr,
                (string) $line->commission_idr,
                (string) $line->reinstatement_idr,
            ];
        }

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }
}
