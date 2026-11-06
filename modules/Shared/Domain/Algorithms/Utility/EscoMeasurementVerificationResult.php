<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Hasil verifikasi pengukuran penghematan energi ESCO (IPMVP Option C)
 * dan performa efisiensi district cooling system.
 */
final class EscoMeasurementVerificationResult
{
    /**
     * @param  int  $baselineKwh  Konsumsi energi baseline historis (kWh)
     * @param  int  $cddFactorBps  Faktor penyesuaian cuaca (CDD) dalam basis points (10000 = 1.0, 11000 = 1.1)
     * @param  int  $adjustedBaselineKwh  Baseline disesuaikan cuaca (kWh)
     * @param  int  $actualKwh  Konsumsi energi aktual periode pelaporan (kWh)
     * @param  int  $grossSavingsKwh  Penghematan energi bruto = adjustedBaseline - actual (kWh)
     * @param  int  $rateIdrPerKwh  Tarif acuan listrik (Rp/kWh)
     * @param  int  $totalSavingsIdr  Nilai total penghematan (Rp)
     * @param  int  $escoShareBps  Porsi bagi hasil ESCO dalam basis points (e.g. 7000 = 70%)
     * @param  int  $escoSavingsShareIdr  Nilai penghematan bagian ESCO (Rp)
     * @param  int  $clientSavingsShareIdr  Nilai penghematan bagian klien/pemilik (Rp)
     * @param  int|null  $thermalKwhTh  District cooling thermal production (kWh_th)
     * @param  int|null  $plantElectricKwh  District cooling plant electric consumption (kWh)
     * @param  int|null  $copBps  Koefisien performa chiller plant dalam bps (e.g. 500 = COP 5.0)
     * @param  int|null  $baselineCopBps  Koefisien performa AC pembanding dalam bps (e.g. 300 = COP 3.0)
     * @param  int|null  $districtCoolingSavingsKwh  Penghematan listrik district cooling dibanding AC mandiri
     * @param  bool  $certified  Status sertifikasi
     */
    public function __construct(
        public readonly int $baselineKwh,
        public readonly int $cddFactorBps,
        public readonly int $adjustedBaselineKwh,
        public readonly int $actualKwh,
        public readonly int $grossSavingsKwh,
        public readonly int $rateIdrPerKwh,
        public readonly int $totalSavingsIdr,
        public readonly int $escoShareBps,
        public readonly int $escoSavingsShareIdr,
        public readonly int $clientSavingsShareIdr,
        public readonly ?int $thermalKwhTh = null,
        public readonly ?int $plantElectricKwh = null,
        public readonly ?int $copBps = null,
        public readonly ?int $baselineCopBps = null,
        public readonly ?int $districtCoolingSavingsKwh = null,
        public readonly bool $certified = true
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'baseline_kwh' => $this->baselineKwh,
            'cdd_factor_bps' => $this->cddFactorBps,
            'adjusted_baseline_kwh' => $this->adjustedBaselineKwh,
            'actual_kwh' => $this->actualKwh,
            'gross_savings_kwh' => $this->grossSavingsKwh,
            'rate_idr_per_kwh' => $this->rateIdrPerKwh,
            'total_savings_idr' => $this->totalSavingsIdr,
            'esco_share_bps' => $this->escoShareBps,
            'esco_savings_share_idr' => $this->escoSavingsShareIdr,
            'client_savings_share_idr' => $this->clientSavingsShareIdr,
            'thermal_kwh_th' => $this->thermalKwhTh,
            'plant_electric_kwh' => $this->plantElectricKwh,
            'cop_bps' => $this->copBps,
            'baseline_cop_bps' => $this->baselineCopBps,
            'district_cooling_savings_kwh' => $this->districtCoolingSavingsKwh,
            'certified' => $this->certified,
        ];
    }
}
