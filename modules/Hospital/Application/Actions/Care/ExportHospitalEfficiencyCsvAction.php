<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\HospitalBed;

final class ExportHospitalEfficiencyCsvAction
{
    private GenerateInpatientSummaryReportAction $reportAction;

    public function __construct(GenerateInpatientSummaryReportAction $reportAction)
    {
        $this->reportAction = $reportAction;
    }

    /**
     * Menghasilkan teks CSV dari statistik indikator Barber-Johnson.
     */
    public function execute(int $periodDays = 30): string
    {
        $totalBeds = max(1, HospitalBed::count());
        $metrics = $this->reportAction->execute($totalBeds, $periodDays);

        $csvLines = [];
        $csvLines[] = 'Indikator,Nilai,Satuan,Status Standar Kemenkes RI';
        $csvLines[] = "Total Kapasitas Ranjang,{$metrics['total_beds']},unit,Kapasitas Aktif";
        $csvLines[] = "Ranjang Terisi,{$metrics['occupied_beds']},unit,Sensur Harian";
        $csvLines[] = "Bed Occupancy Rate (BOR)," . ($metrics['bor_percent'] / 100) . "%,persen," . ($metrics['bor_percent'] >= 7500 && $metrics['bor_percent'] <= 8500 ? 'IDEAL' : 'PERINGATAN');
        $csvLines[] = "Average Length of Stay (ALOS)," . ($metrics['alos_days'] / 1000) . ",hari,Standar 3 - 9 Hari";
        $csvLines[] = "Turn Over Interval (TOI)," . ($metrics['toi_days'] / 1000) . ",hari,Standar 1 - 3 Hari";
        $csvLines[] = "Bed Turn Over (BTO)," . ($metrics['bto_times'] / 1000) . ",kali,Standar >= 30 kali/tahun";
        $csvLines[] = "Evaluasi Barber-Johnson,{$metrics['barber_johnson_status']},kategori,Hasil Komputasi Sertifikat B4";

        return implode("\n", $csvLines) . "\n";
    }
}
