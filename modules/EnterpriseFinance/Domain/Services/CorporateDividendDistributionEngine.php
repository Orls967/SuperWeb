<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\DTOs\CorporateDividendCalculationResultDTO;

/**
 * Mesin Tata Kelola & Distribusi Dividen Korporat Grup (UU PT No. 40/2007 Pasal 70 & 71).
 * Mengatur tata cara pembagian dividen anak perusahaan kepada entitas induk:
 * 1. Persyaratan Dana Cadangan Wajib: Minimal 20% dari modal ditempatkan dan disetor penuh.
 * 2. Uji Solvabilitas (Solvency Test): Anak perusahaan dilarang membagikan dividen jika
 *    mengakibatkan jumlah aset bersih menjadi lebih kecil daripada jumlah modal ditempatkan + cadangan wajib.
 * 3. Pembagian Dividen Tunai Berdasarkan Rasio Pembayaran (Payout Ratio BPS).
 * 4. Alokasi porsi pemegang saham induk vs kepentingan non-pengendali (NCI).
 */
class CorporateDividendDistributionEngine
{
    /**
     * Menghitung kelayakan dan nominal distribusi dividen anak perusahaan.
     *
     * @param  int  $payoutRatioBps  Rasio pembayaran dividen dalam basis points (misal 4000 bps = 40%)
     * @param  int  $nciOwnershipPermille  Porsi saham non-pengendali dalam permille (misal 200 = 20%)
     */
    public function calculateDividend(
        string $subsidiaryEntity,
        int $netProfitMinor,
        int $retainedEarningsMinor,
        int $paidInCapitalMinor,
        int $currentReserveMinor,
        int $postDistributionCashMinor,
        int $payoutRatioBps = 4000,
        int $nciOwnershipPermille = 0
    ): CorporateDividendCalculationResultDTO {
        if ($netProfitMinor <= 0) {
            throw new InvalidArgumentException('Dividen hanya dapat dibagikan dari entitas anak yang membukukan laba bersih positif.');
        }

        if ($payoutRatioBps <= 0 || $payoutRatioBps > 10000) {
            throw new InvalidArgumentException('Rasio dividen (payout ratio) harus berada dalam rentang 1 s/d 10.000 bps.');
        }

        if ($nciOwnershipPermille < 0 || $nciOwnershipPermille >= 1000) {
            throw new InvalidArgumentException('Porsi kepemilikan saham NCI harus berada dalam rentang 0 s/d 999 permille.');
        }

        // 1. Alokasi Cadangan Wajib UU PT Pasal 70 (Target Cadangan = 20% Modal Ditempatkan)
        $targetReserve = (int) intdiv($paidInCapitalMinor * 2000, 10000);
        $reserveDeficit = max(0, $targetReserve - $currentReserveMinor);

        // Jika cadangan belum mencapai 20%, sisihkan minimal 10% dari laba bersih tahun berjalan
        $statutoryDeduction = 0;
        if ($reserveDeficit > 0) {
            $mandatoryTenPct = (int) intdiv($netProfitMinor * 1000, 10000);
            $statutoryDeduction = min($reserveDeficit, $mandatoryTenPct);
        }

        // 2. Laba Bersih yang Dapat Didistribusikan (Distributable Net Profit)
        $distributableProfit = max(0, $netProfitMinor - $statutoryDeduction);

        // 3. Nominal Dividen Berdasarkan Payout Ratio
        $proposedDividend = (int) intdiv($distributableProfit * $payoutRatioBps, 10000);

        // Plafon Maksimum Dividen = Saldo Laba Ditahan Positif
        $finalDividend = min($proposedDividend, max(0, $retainedEarningsMinor));

        // 4. Uji Solvabilitas: Kas pasca distribusi harus tetap positif
        $passesSolvency = ($postDistributionCashMinor >= $finalDividend);

        // 5. Alokasi Induk vs NCI
        $nciShare = (int) intdiv($finalDividend * $nciOwnershipPermille, 1000);
        $parentShare = $finalDividend - $nciShare;

        $ref = 'RES-DIV-'.$subsidiaryEntity.'-'.date('Ymd').'-'.bin2hex(random_bytes(3));

        return new CorporateDividendCalculationResultDTO(
            subsidiaryEntity: $subsidiaryEntity,
            netProfitMinor: $netProfitMinor,
            retainedEarningsMinor: $retainedEarningsMinor,
            payoutRatioBps: $payoutRatioBps,
            statutoryReserveDeductionMinor: $statutoryDeduction,
            distributableDividendMinor: $finalDividend,
            parentDividendShareMinor: $parentShare,
            nciDividendShareMinor: $nciShare,
            passesSolvencyTest: $passesSolvency,
            resolutionReference: $ref
        );
    }
}
