<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;

class CargoManifestCsvExporter
{
    /**
     * Ekspor manifes kargo udara resmi (Air Cargo Manifest) termasuk klasifikasi DG dan rantai kustodi.
     */
    public function export(int $flightId): string
    {
        $flight = AviationFlight::with(['operator', 'originAirport', 'destinationAirport'])->findOrFail($flightId);

        $consignments = AviationCargoConsignment::where('flight_id', $flightId)
            ->whereIn('status', ['accepted', 'dg_cleared', 'loaded', 'in_flight', 'delivered'])
            ->orderBy('consignment_number')
            ->get();

        $rows = [];
        $rows[] = ['MANIFES RESMI MUATAN KARGO UDARA (AIR CARGO MANIFEST)'];
        $rows[] = ['Nomor Penerbangan', $flight->flight_number];
        $rows[] = ['Rute', ($flight->originAirport->iata_code ?? '').' -> '.($flight->destinationAirport->iata_code ?? '')];
        $rows[] = ['Total Item Kargo', (string) $consignments->count()];
        $rows[] = ['Total Berat Kotor (kg)', number_format($consignments->sum('weight_g') / 1000, 2, '.', '')];
        $rows[] = [];

        $rows[] = [
            'AWB / Nomor Konsinyasi',
            'Sifat Barang (Nature of Goods)',
            'Berat Kotor (kg)',
            'Volume (m3)',
            'Kontainer ULD',
            'Barang Berbahaya (DG)',
            'Kelas DG',
            'Nomor UN',
            'Suhu Min (°C)',
            'Suhu Max (°C)',
            'Status',
            'Hash Rantai Kustodi',
        ];

        foreach ($consignments as $c) {
            $rows[] = [
                $c->consignment_number,
                str_replace(["\r", "\n", ','], ' ', $c->nature_of_goods),
                number_format($c->weight_g / 1000, 2, '.', ''),
                number_format($c->volume_mm3 / 1000000000, 3, '.', ''),
                $c->uld_container_code ?? 'BULK',
                $c->is_dg ? 'YA' : 'TIDAK',
                $c->dg_class ?? '-',
                $c->un_number ?? '-',
                $c->required_temperature_min_c10 !== null ? (string) ($c->required_temperature_min_c10 / 10) : '-',
                $c->required_temperature_max_c10 !== null ? (string) ($c->required_temperature_max_c10 / 10) : '-',
                $c->status,
                $c->custody_hash_chain ?? '',
            ];
        }

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputs($output, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : '';
    }
}
