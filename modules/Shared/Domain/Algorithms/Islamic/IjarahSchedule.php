<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Apportionment\LargestRemainder;

/**
 * B1 Islamic\IjarahSchedule
 *
 * Menghitung jadwal sewa (ujrah) per periode, amortisasi penyusutan garis lurus aset ijarah,
 * serta opsi IMBT (hibah/jual di akhir).
 *
 * Kompleksitas: O(M) di mana M adalah tenor bulan.
 */
final class IjarahSchedule
{
    public function __construct(
        private readonly LargestRemainder $largestRemainder = new LargestRemainder
    ) {}

    /**
     * @param  int  $assetCostIdr  Biaya perolehan aset riil (integer IDR)
     * @param  int  $tenorMonths  Jangka waktu sewa (bulan)
     * @param  int  $totalUjrahIdr  Total pendapatan sewa ujrah yang disepakati (integer IDR)
     * @param  int  $residualOptionPriceIdr  Harga pelaksanaan opsi beli IMBT di akhir periode (integer IDR)
     */
    public function generate(
        int $assetCostIdr,
        int $tenorMonths,
        int $totalUjrahIdr,
        int $residualOptionPriceIdr = 0
    ): IjarahResult {
        if ($assetCostIdr <= 0) {
            throw new InvalidArgumentException("Biaya aset ijarah harus positif: {$assetCostIdr}");
        }
        if ($tenorMonths <= 0) {
            throw new InvalidArgumentException("Tenor ijarah harus minimal 1 bulan: {$tenorMonths}");
        }
        if ($totalUjrahIdr <= 0) {
            throw new InvalidArgumentException("Total ujrah ijarah harus positif: {$totalUjrahIdr}");
        }
        if ($residualOptionPriceIdr < 0) {
            throw new InvalidArgumentException("Harga opsi residual tidak boleh negatif: {$residualOptionPriceIdr}");
        }

        // Distribusi ujrah per bulan (LargestRemainder Hamilton)
        $ujrahItems = [];
        for ($m = 1; $m <= $tenorMonths; $m++) {
            $ujrahItems[] = [
                'key' => sprintf('%02d', $m),
                'weight' => 1,
            ];
        }
        $ujrahAlloc = $this->largestRemainder->apportion($totalUjrahIdr, $ujrahItems);

        // Penyusutan garis lurus per bulan atas (biaya - nilai sisa/opsi)
        $depreciableBase = max(0, $assetCostIdr - $residualOptionPriceIdr);
        $deprecAlloc = $this->largestRemainder->apportion($depreciableBase, $ujrahItems);

        $periods = [];
        $accumDeprec = 0;
        $sumUjrah = 0;

        for ($m = 1; $m <= $tenorMonths; $m++) {
            $key = sprintf('%02d', $m);
            $ujrahMonth = $ujrahAlloc[$key];
            $deprecMonth = $deprecAlloc[$key];

            $accumDeprec += $deprecMonth;
            $bookValue = $assetCostIdr - $accumDeprec;
            $sumUjrah += $ujrahMonth;

            $periods[] = [
                'period' => $m,
                'ujrah_idr' => $ujrahMonth,
                'depreciation_idr' => $deprecMonth,
                'accumulated_depreciation_idr' => $accumDeprec,
                'carrying_book_value_idr' => $bookValue,
            ];
        }

        $result = new IjarahResult(
            assetCostIdr: $assetCostIdr,
            tenorMonths: $tenorMonths,
            totalUjrahIdr: $totalUjrahIdr,
            residualOptionPriceIdr: $residualOptionPriceIdr,
            periods: $periods,
            sumUjrahIdr: $sumUjrah,
            sumDepreciationIdr: $accumDeprec,
            endingBookValueIdr: $assetCostIdr - $accumDeprec
        );

        (new IjarahScheduleCertificateVerifier)->verify($result);

        return $result;
    }
}
