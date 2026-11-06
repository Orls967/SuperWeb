<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Hasil perhitungan neraca air standar IWA (International Water Association).
 * Semua volume dalam satuan integer Liter (L).
 */
final class WaterBalanceResult
{
    /**
     * @param  int  $systemInputVolumeL  Total air masuk ke sistem (SIV)
     * @param  int  $billedAuthorizedL  Konsumsi berbayar resmi (metered + unmetered)
     * @param  int  $unbilledAuthorizedL  Konsumsi resmi tidak berbayar (pemadam, operasional)
     * @param  int  $apparentLossesL  Kehilangan komersial (pencurian, meter under-registration)
     * @param  int  $realLossesL  Kehilangan fisik (kebocoran pipa utama, tandon, sambungan rumah)
     * @param  int  $totalLossesL  Total kehilangan air = apparent + real
     * @param  int  $nonRevenueWaterL  Total NRW = SIV - billedAuthorized
     * @param  int  $nrwBasisPoints  Persentase NRW dalam basis points (100 bps = 1.00%)
     * @param  bool  $certified  Status verifikasi invarian
     */
    public function __construct(
        public readonly int $systemInputVolumeL,
        public readonly int $billedAuthorizedL,
        public readonly int $unbilledAuthorizedL,
        public readonly int $apparentLossesL,
        public readonly int $realLossesL,
        public readonly int $totalLossesL,
        public readonly int $nonRevenueWaterL,
        public readonly int $nrwBasisPoints,
        public readonly bool $certified
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'system_input_volume_l' => $this->systemInputVolumeL,
            'billed_authorized_l' => $this->billedAuthorizedL,
            'unbilled_authorized_l' => $this->unbilledAuthorizedL,
            'apparent_losses_l' => $this->apparentLossesL,
            'real_losses_l' => $this->realLossesL,
            'total_losses_l' => $this->totalLossesL,
            'non_revenue_water_l' => $this->nonRevenueWaterL,
            'nrw_basis_points' => $this->nrwBasisPoints,
            'certified' => $this->certified,
        ];
    }
}
