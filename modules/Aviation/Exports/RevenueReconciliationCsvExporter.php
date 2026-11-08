<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationTicket;

class RevenueReconciliationCsvExporter extends BaseCsvExporter
{
    /**
     * Ekspor laporan rekonsiliasi pendapatan aviasi: tiket terbang, unearned, refund, dan pajak.
     */
    public function export(string $startDate, string $endDate): string
    {
        $flights = AviationFlight::with(['originAirport', 'destinationAirport'])
            ->whereBetween('departure_time', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->get();

        $rows = [];
        $rows[] = ['REKONSILIASI PENDAPATAN & KEUANGAN AVIASI'];
        $rows[] = ['Periode', "{$startDate} s.d. {$endDate}"];
        $rows[] = ['Tanggal Ekspor', date('Y-m-d H:i:s')];
        $rows[] = [];

        $rows[] = [
            'Flight Number',
            'Rute',
            'Status Penerbangan',
            'Jadwal Berangkat',
            'Tiket Terbit (Count)',
            'Tiket Terbang (Count)',
            'Tiket Batal (Count)',
            'Pendapatan Diakui (IDR)',
            'Pendapatan Ditangguhkan (IDR)',
            'Pajak Bandara (IDR)',
            'Total Nilai Refund (IDR)',
        ];

        foreach ($flights as $f) {
            $tickets = AviationTicket::where('flight_id', $f->id)->get();
            $flown = $tickets->where('status', 'flown');
            $issued = $tickets->whereIn('status', ['issued', 'checked_in', 'boarded']);
            $cancelled = $tickets->where('status', 'cancelled');

            $flownRev = $flown->sum('base_fare_minor');
            $unearnedRev = $issued->sum('base_fare_minor');
            $taxCollected = $tickets->sum('tax_minor');

            $ticketIds = $tickets->pluck('id');
            $refundSum = AviationRefund::whereIn('ticket_id', $ticketIds)->sum('net_refund_minor');

            $rows[] = [
                $f->flight_number,
                ($f->originAirport->iata_code ?? '').'-'.($f->destinationAirport->iata_code ?? ''),
                $f->status,
                (string) $f->departure_time,
                (string) $tickets->count(),
                (string) $flown->count(),
                (string) $cancelled->count(),
                number_format($flownRev / 100, 2, '.', ''),
                number_format($unearnedRev / 100, 2, '.', ''),
                number_format($taxCollected / 100, 2, '.', ''),
                number_format($refundSum / 100, 2, '.', ''),
            ];
        }

        return $this->generateCsvString($rows);
    }
}
