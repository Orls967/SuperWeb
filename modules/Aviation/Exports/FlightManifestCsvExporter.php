<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;

class FlightManifestCsvExporter extends BaseCsvExporter
{
    /**
     * Ekspor manifes penumpang resmi sebelum keberangkatan penerbangan.
     * Nama penumpang didekripsi secara legal untuk kebutuhan manifes otoritas bandara.
     */
    public function export(int $flightId): string
    {
        $flight = AviationFlight::with(['operator', 'originAirport', 'destinationAirport', 'aircraft'])
            ->findOrFail($flightId);

        $tickets = AviationTicket::where('flight_id', $flightId)
            ->whereIn('status', ['issued', 'checked_in', 'boarded', 'flown'])
            ->orderBy('seat_assignment')
            ->get();

        $rows = [];
        // Header Informasi Penerbangan
        $rows[] = ['MANIFES RESMI PENUMPANG (PASSENGER MANIFEST)'];
        $rows[] = ['Nomor Penerbangan', $flight->flight_number];
        $rows[] = ['Operator', ($flight->operator->name ?? '').' ('.($flight->operator->code ?? '').')'];
        $rows[] = ['Rute', ($flight->originAirport->iata_code ?? '').' -> '.($flight->destinationAirport->iata_code ?? '')];
        $rows[] = ['Waktu Berangkat', (string) $flight->departure_time];
        $rows[] = ['Pesawat', ($flight->aircraft->registration_mark ?? 'TBD').' ('.($flight->aircraft->icao_type ?? '').')'];
        $rows[] = ['Total Penumpang Terdaftar', (string) $tickets->count()];
        $rows[] = []; // Baris kosong

        // Tabel Penumpang
        $rows[] = [
            'No',
            'Nomor Tiket',
            'Kursi',
            'Kabin',
            'Kelas Tarif',
            'Status',
            'Nama Lengkap Penumpang',
            'Blind Index Hash (PDP)',
            'Total Tarif (IDR)',
        ];

        $counter = 1;
        foreach ($tickets as $t) {
            $rows[] = [
                (string) $counter++,
                $t->ticket_number,
                $t->seat_assignment ?? 'UNASSIGNED',
                $t->cabin,
                $t->fare_basis,
                $t->status,
                $t->pii_name_encrypted, // Eloquent encrypted cast otomatis mendekripsi
                $t->pii_blind_index ?? '',
                number_format($t->total_fare_minor / 100, 2, '.', ''),
            ];
        }

        return $this->generateCsvString($rows);
    }
}
