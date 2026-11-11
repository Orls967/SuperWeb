<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Mesin Uji Penurunan Nilai Goodwill Korporat (Goodwill Impairment Testing) (54.8 - PSAK 48 / IAS 36).
 * Menguji apakah nilai tercatat (Carrying Amount) Unit Penghasil Kas (CGU) melebihi
 * jumlah terpulihkan (Recoverable Amount), yang merupakan nilai tertinggi antara:
 * 1. Nilai Wajar Dikurangi Biaya Pelepasan (Fair Value Less Costs of Disposal).
 * 2. Nilai Pakai (Value in Use) berbasis proyeksi arus kas terdiskonto (DCF).
 */
class GoodwillImpairmentAssessmentEngine
{
    /**
     * Mengeksekusi pengujian penurunan nilai goodwill tahunan atas Unit Penghasil Kas (CGU).
     *
     * @param  int  $carryingAmountWithoutGoodwillMinor  Nilai tercatat aset CGU di luar goodwill
     * @param  int  $carryingGoodwillMinor  Nilai tercatat goodwill yang dialokasikan ke CGU
     * @param  list<int>  $projectedCashFlowsMinor  Proyeksi arus kas masa depan (misal 5 tahun)
     * @param  int  $discountRateBps  Tingkat diskonto WACC dalam basis points (misal 1000 bps = 10%)
     * @param  int  $terminalGrowthRateBps  Tingkat pertumbuhan terminal jangka panjang (misal 200 bps = 2%)
     * @return array{
     *     cgu_code: string,
     *     carrying_amount_total_minor: int,
     *     carrying_goodwill_minor: int,
     *     value_in_use_minor: int,
     *     impairment_loss_minor: int,
     *     adjusted_goodwill_minor: int,
     *     is_impaired: bool,
     *     accounting_treatment: string
     * }
     */
    public function assessImpairment(
        string $cguCode,
        int $carryingAmountWithoutGoodwillMinor,
        int $carryingGoodwillMinor,
        array $projectedCashFlowsMinor,
        int $discountRateBps = 1000,
        int $terminalGrowthRateBps = 200
    ): array {
        if ($carryingGoodwillMinor < 0 || $carryingAmountWithoutGoodwillMinor < 0) {
            throw new InvalidArgumentException('Nilai buku aset CGU tidak boleh bernilai negatif.');
        }

        if ($discountRateBps <= $terminalGrowthRateBps) {
            throw new InvalidArgumentException('Tingkat diskonto WACC harus lebih tinggi dari tingkat pertumbuhan terminal.');
        }

        $totalCarryingAmount = $carryingAmountWithoutGoodwillMinor + $carryingGoodwillMinor;

        // 1. Menghitung Nilai Pakai (Value in Use - DCF)
        $presentValueSum = 0;
        $year = 1;
        $lastCashFlow = 0;

        foreach ($projectedCashFlowsMinor as $cf) {
            $lastCashFlow = $cf;
            // Faktor diskonto: (1 + r)^year dihampirkan via pecahan rasional integer
            // PV = CF / (1 + r)^year
            // Untuk tahun t: pembagi = (10000 + discountRateBps)^t / 10000^t
            $discountFactorPermille = 1000;
            for ($i = 0; $i < $year; $i++) {
                $discountFactorPermille = (int) intdiv($discountFactorPermille * 10000, 10000 + $discountRateBps);
            }

            $pv = (int) intdiv($cf * $discountFactorPermille, 1000);
            $presentValueSum += $pv;
            $year++;
        }

        // 2. Terminal Value Gordon Growth Model = (Last CF * (1 + g)) / (r - g)
        $terminalNumerator = (int) intdiv($lastCashFlow * (10000 + $terminalGrowthRateBps), 10000);
        $spreadBps = $discountRateBps - $terminalGrowthRateBps;
        $terminalValue = (int) intdiv($terminalNumerator * 10000, $spreadBps);

        // Diskonkan Terminal Value ke Present Value
        $terminalDiscountFactor = 1000;
        for ($i = 0; $i < count($projectedCashFlowsMinor); $i++) {
            $terminalDiscountFactor = (int) intdiv($terminalDiscountFactor * 10000, 10000 + $discountRateBps);
        }
        $pvTerminal = (int) intdiv($terminalValue * $terminalDiscountFactor, 1000);

        $valueInUse = $presentValueSum + $pvTerminal;

        // 3. Evaluasi Penurunan Nilai: Impairment Loss = max(0, Carrying Amount - Value in Use)
        $impairmentLoss = max(0, $totalCarryingAmount - $valueInUse);

        // Sesuai PSAK 48: Kerugian penurunan nilai pertama-tama dialokasikan untuk menghapus goodwill
        $goodwillWriteDown = min($carryingGoodwillMinor, $impairmentLoss);
        $adjustedGoodwill = $carryingGoodwillMinor - $goodwillWriteDown;

        $isImpaired = ($goodwillWriteDown > 0);
        $treatment = $isImpaired
            ? "Beban penurunan nilai goodwill sebesar Rp {$goodwillWriteDown} diakui di Laba Rugi (tidak dapat dipulihkan di periode mendatang)."
            : 'Nilai terpulihkan CGU melampaui nilai tercatat. Tidak ada pengakuan rugi penurunan nilai goodwill.';

        return [
            'cgu_code' => $cguCode,
            'carrying_amount_total_minor' => $totalCarryingAmount,
            'carrying_goodwill_minor' => $carryingGoodwillMinor,
            'value_in_use_minor' => $valueInUse,
            'impairment_loss_minor' => $goodwillWriteDown,
            'adjusted_goodwill_minor' => $adjustedGoodwill,
            'is_impaired' => $isImpaired,
            'accounting_treatment' => $treatment,
        ];
    }
}
