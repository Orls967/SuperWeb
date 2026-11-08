<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationSlot;

class SlotAllocationCsvExporter
{
    /**
     * Ekspor alokasi slot bandara per season ke dalam format CSV UTF-8.
     *
     * @param int $airportId
     * @param string $seasonCode
     * @return string Konten mentah CSV
     */
    public function export(int $airportId, string $seasonCode): string
    {
        $slots = AviationSlot::with(['airport', 'operator'])
            ->where('airport_id', $airportId)
            ->where('season_code', $seasonCode)
            ->orderBy('day_of_week')
            ->orderBy('allocated_time_utc')
            ->get();

        $rows = [];
        $rows[] = [
            'Slot ID',
            'Bandara IATA',
            'Operator IATA',
            'Operator Nama',
            'Season',
            'Hari (1=Sen, 7=Ming)',
            'Waktu UTC',
            'Tipe Gerakan',
            'Status',
            'Hak Historis (80/20)',
            'Rasio Pemanfaatan (bps)',
            'Catatan Koordinator',
        ];

        foreach ($slots as $slot) {
            $rows[] = [
                (string) $slot->id,
                $slot->airport->iata_code ?? 'N/A',
                $slot->operator->code ?? 'N/A',
                $slot->operator->name ?? 'N/A',
                $slot->season_code,
                (string) $slot->day_of_week,
                $slot->allocated_time_utc,
                $slot->movement_type,
                $slot->status,
                $slot->is_historic ? 'YA' : 'TIDAK',
                (string) $slot->usage_rate_bps,
                str_replace(["\r", "\n", ','], ' ', $slot->notes ?? ''),
            ];
        }

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        // BOM UTF-8 untuk kompatibilitas Excel
        fputs($output, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent !== false ? $csvContent : '';
    }
}
