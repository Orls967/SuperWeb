<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationFlight;

class YieldPerformanceCsvExporter extends BaseCsvExporter
{
    /**
     * Ekspor status proteksi dan keterisian ember tarif penerbangan.
     */
    public function exportFlightYield(int $flightId): string
    {
        $flight = AviationFlight::with('yieldBuckets')->findOrFail($flightId);

        $headers = [
            'Nomor Penerbangan',
            'Kapasitas Total',
            'Kode Bucket',
            'Kelas Kabin',
            'Tarif Dasar (IDR)',
            'Kapasitas Dialokasikan',
            'Tiket Terjual',
            'Sisa Kursi',
            'Tingkat Proteksi',
            'Status Bucket',
        ];

        $rows = [$headers];

        foreach ($flight->yieldBuckets as $bucket) {
            $rows[] = [
                $flight->flight_number,
                (string) $flight->capacity_seats,
                $bucket->bucket_code,
                $bucket->cabin_class,
                (string) round($bucket->base_price_minor_unit / 100),
                (string) $bucket->allocated_capacity,
                (string) $bucket->sold_count,
                (string) $bucket->availableSeats(),
                (string) $bucket->protection_level,
                $bucket->is_closed ? 'TERTUTUP' : 'TERBUKA',
            ];
        }

        return $this->generateCsvString($rows);
    }
}
