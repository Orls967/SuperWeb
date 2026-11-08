<?php

declare(strict_types=1);

namespace Modules\Aviation\Exports;

use Modules\Aviation\Domain\Models\AviationDisruptionCase;

class IrropsManifestCsvExporter extends BaseCsvExporter
{
    /**
     * Ekspor daftar manifest penumpang terdampak kasus disrupsi ke format CSV.
     */
    public function exportCaseManifest(int $disruptionCaseId): string
    {
        $case = AviationDisruptionCase::with(['flight', 'passengers.careVouchers'])
            ->findOrFail($disruptionCaseId);

        $headers = [
            'Nomor Kasus',
            'Nomor Penerbangan',
            'Kategori Insiden',
            'Kategori PM89',
            'Durasi Delay (Mnt)',
            'Nama Penumpang',
            'Tier Loyalitas',
            'Skor Prioritas',
            'Status Reakomodasi',
            'Ref Hotel',
            'Jumlah Voucher',
        ];

        $rows = [$headers];

        foreach ($case->passengers as $pax) {
            $rows[] = [
                $case->case_number,
                $case->flight->flight_number ?? 'N/A',
                $case->incident_category,
                $case->regulatory_category,
                (string) $case->delay_minutes,
                $pax->passenger_name,
                strtoupper($pax->loyalty_tier),
                (string) $pax->priority_score,
                $pax->reaccommodation_status,
                $pax->hotel_reservation_ref ?? '-',
                (string) $pax->careVouchers->count(),
            ];
        }

        return $this->generateCsvString($rows);
    }
}
