<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\Microgrid;

/**
 * Layanan Kartu Skor Ketahanan Daya & Indeks Keandalan (IEEE 1366: SAIDI, SAIFI, CAIDI).
 * Menghitung frekuensi dan durasi pemadaman rata-rata sistem serta Value of Lost Load (VoLL).
 */
final class ResilienceScorecardAndReliabilityIndexService
{
    /**
     * Hitung indeks keandalan microgrid tahunan.
     *
     * @param  int  $totalServedCustomers  Jumlah pelanggan/tenant terlayani
     * @param  int  $totalSustainedInterruptionMinutes  Total menit padam berkelanjutan (> 5 menit)
     * @param  int  $totalInterruptionEvents  Jumlah kejadian pemadaman
     * @param  int  $momentaryInterruptionEvents  Jumlah interupsi sesaat (< 5 menit)
     * @param  int  $valueLostLoadIdrPerKwh  Biaya kerugian per kWh padam (misal Rp 45.000 / kWh)
     * @return array<string, mixed>
     */
    public function generateScorecard(
        string $microgridCode,
        int $totalServedCustomers,
        int $totalSustainedInterruptionMinutes,
        int $totalInterruptionEvents,
        int $momentaryInterruptionEvents,
        int $valueLostLoadIdrPerKwh = 45000
    ): array {
        if ($totalServedCustomers <= 0) {
            throw new InvalidArgumentException('Jumlah pelanggan harus > 0.');
        }

        $microgrid = Microgrid::where('microgrid_code', $microgridCode)->firstOrFail();

        // 1. SAIFI (System Average Interruption Frequency Index) = Total pemadaman / Total pelanggan
        // Dalam basis points (1 bps = 0.0001 event/pelanggan)
        $saifiBps = (int) intdiv($totalInterruptionEvents * 10000, $totalServedCustomers);

        // 2. SAIDI (System Average Interruption Duration Index) = Total menit padam / Total pelanggan
        // Menit padam rata-rata per pelanggan
        $saidiMinutesPerCustomer = (int) intdiv($totalSustainedInterruptionMinutes, $totalServedCustomers);

        // 3. CAIDI (Customer Average Interruption Duration Index) = SAIDI / SAIFI
        $caidiMinutesPerInterruption = $totalInterruptionEvents > 0
            ? (int) intdiv($totalSustainedInterruptionMinutes, $totalInterruptionEvents)
            : 0;

        // 4. MAIFI (Momentary Average Interruption Frequency Index)
        $maifiBps = (int) intdiv($momentaryInterruptionEvents * 10000, $totalServedCustomers);

        // 5. Ketersediaan Sistem (ASAI - Average Service Availability Index)
        // 1 tahun = 525.600 menit -> ASAI = (525.600 - SAIDI) / 525.600 (dalam bps, misal 9995 = 99.95%)
        $annualCustomerMinutes = 525600;
        $asaiBps = (int) intdiv(($annualCustomerMinutes - $saidiMinutesPerCustomer) * 10000, $annualCustomerMinutes);

        // 6. Value of Lost Load (VoLL) - Estimasi Kerugian Ekonomi Pemadaman
        // Rata-rata beban padam diperkirakan dari kapasitas rata-rata
        $averageLoadKw = (int) intdiv($microgrid->total_capacity_kw, 2);
        $lostEnergyKwh = (int) intdiv($averageLoadKw * $totalSustainedInterruptionMinutes, 60);
        $totalVollCostIdr = $lostEnergyKwh * $valueLostLoadIdrPerKwh;

        $payload = sprintf(
            'RELIABILITY-SCORECARD|MG:%s|SAIFI:%d|SAIDI:%d|CAIDI:%d|ASAI:%d|VOLL:%d',
            $microgrid->microgrid_code,
            $saifiBps,
            $saidiMinutesPerCustomer,
            $caidiMinutesPerInterruption,
            $asaiBps,
            $totalVollCostIdr
        );
        $scorecardHash = hash('sha256', $payload);

        return [
            'microgrid_code' => $microgrid->microgrid_code,
            'total_customers' => $totalServedCustomers,
            'saifi_events_bps' => $saifiBps,
            'saidi_minutes_per_customer' => $saidiMinutesPerCustomer,
            'caidi_minutes_per_event' => $caidiMinutesPerInterruption,
            'maifi_events_bps' => $maifiBps,
            'service_availability_asai_bps' => $asaiBps,
            'availability_percentage' => ($asaiBps / 100).'%',
            'value_of_lost_load_idr' => $totalVollCostIdr,
            'scorecard_hash' => $scorecardHash,
        ];
    }
}
