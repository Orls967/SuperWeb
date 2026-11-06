<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Domain\Models\BessAsset;

/**
 * Layanan Optimasi Multi-Fungsi (Value Stacking) & Pemangkasan Beban Puncak (Peak Shaving) BESS.
 * Menggabungkan pemangkasan biaya kapasitas langganan PLN (demand charge reduction),
 * arbitrase tarif WBP, dan penyediaan cadangan putar (spinning reserve) dalam satu siklus harian.
 */
final class BessPeakShavingAndValueStackingService
{
    /**
     * Hitung jadwal value-stacking dan penghematan biaya total.
     *
     * @param  int  $subscribedPlnContractKw  Daya kontrak langganan PLN (kW)
     * @param  int  $demandChargeIdrPerKw  Tarif beban daya PLN per kW per bulan (misal Rp 40.000 / kW)
     * @param  list<int>  $hourlyLoadForecastKw  Prediksi profil beban 24 jam kawasan (kW)
     * @param  list<int>  $hourlyTariffIdrPerKwh  Profil tarif listrik per jam
     * @return array<string, mixed>
     */
    public function optimizeValueStacking(
        string $bessCode,
        int $subscribedPlnContractKw,
        int $demandChargeIdrPerKw,
        array $hourlyLoadForecastKw,
        array $hourlyTariffIdrPerKwh
    ): array {
        if (count($hourlyLoadForecastKw) !== 24 || count($hourlyTariffIdrPerKwh) !== 24) {
            throw new InvalidArgumentException('Profil beban dan tarif harus 24 jam.');
        }

        $bess = BessAsset::where('bess_code', $bessCode)->firstOrFail();

        $maxDischargeKw = (int) $bess->max_discharge_power_kw;
        $capacityKwh = (int) $bess->capacity_kwh;

        // 1. Peak Shaving Target:
        // Puncak beban kotor tanpa baterai
        $unmitigatedPeakLoadKw = max($hourlyLoadForecastKw);

        // Ambang batas pemangkasan: pangkas kelebihan di atas daya kontrak atau beban rata-rata
        $shavingThresholdKw = min($subscribedPlnContractKw, (int) ($unmitigatedPeakLoadKw - $maxDischargeKw));
        $shavingThresholdKw = max(0, $shavingThresholdKw);

        $mitigatedPeakLoadKw = $unmitigatedPeakLoadKw;
        $totalShavedEnergyKwh = 0;
        $shavingSavingsIdr = 0;

        foreach ($hourlyLoadForecastKw as $hour => $loadKw) {
            if ($loadKw > $subscribedPlnContractKw) {
                $exceedingKw = $loadKw - $subscribedPlnContractKw;
                $dischargeKw = min($maxDischargeKw, $exceedingKw);
                $totalShavedEnergyKwh += $dischargeKw;
            }
        }

        // Penghematan penalti over-contract / demand charge:
        // Demand charge savings = peak reduced (kW) * demandChargeIdrPerKw
        $peakReductionKw = max(0, $unmitigatedPeakLoadKw - max($shavingThresholdKw, $subscribedPlnContractKw));
        $demandChargeSavingsIdr = $peakReductionKw * $demandChargeIdrPerKw;

        // 2. Arbitrase WBP vs LWBP
        $minTariff = min($hourlyTariffIdrPerKwh);
        $maxTariff = max($hourlyTariffIdrPerKwh);
        $spreadIdrPerKwh = max(0, $maxTariff - $minTariff);
        $arbitrageSavingsIdr = (int) intdiv($capacityKwh * 70, 100) * $spreadIdrPerKwh; // 70% DoD

        // 3. Cadangan Putar (Spinning Reserve Payment - 10% nilai kapasitas)
        $spinningReserveBonusIdr = (int) intdiv($capacityKwh * 5000, 100);

        $totalStackedBenefitIdr = $demandChargeSavingsIdr + $arbitrageSavingsIdr + $spinningReserveBonusIdr;

        $payload = sprintf(
            'BESS-STACKING|BESS:%s|UNMIT_PEAK:%d|RED_KW:%d|DEM_SAV:%d|ARB_SAV:%d|TOTAL:%d',
            $bess->bess_code,
            $unmitigatedPeakLoadKw,
            $peakReductionKw,
            $demandChargeSavingsIdr,
            $arbitrageSavingsIdr,
            $totalStackedBenefitIdr
        );
        $stackingHash = hash('sha256', $payload);

        return [
            'bess_code' => $bess->bess_code,
            'unmitigated_peak_load_kw' => $unmitigatedPeakLoadKw,
            'peak_reduction_kw' => $peakReductionKw,
            'demand_charge_savings_idr' => $demandChargeSavingsIdr,
            'arbitrage_savings_idr' => $arbitrageSavingsIdr,
            'spinning_reserve_bonus_idr' => $spinningReserveBonusIdr,
            'total_stacked_benefit_idr' => $totalStackedBenefitIdr,
            'total_shaved_energy_kwh' => $totalShavedEnergyKwh,
            'stacking_hash' => $stackingHash,
        ];
    }
}
