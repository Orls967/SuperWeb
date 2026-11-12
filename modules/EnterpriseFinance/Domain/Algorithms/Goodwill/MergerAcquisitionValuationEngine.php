<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Goodwill;

use InvalidArgumentException;

/**
 * Mesin Valuasi Merger & Akuisisi (M&A), Alokasi Harga Beli (Purchase Price Allocation / PPA) & Uji Penurunan Nilai Goodwill.
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class MergerAcquisitionValuationEngine
{
    /**
     * Mengeksekusi Alokasi Harga Pembelian (Purchase Price Allocation / PPA) sesuai PSAK 22 / IFRS 3.
     *
     * Goodwill = Harga Beli - Nilai Wajar Aset Bersih Teridentifikasi
     *
     * @param  int  $purchaseConsiderationMinor  Nilai imbalan pengalihan (harga beli akuisisi).
     * @param  int  $bookValueNetAssetsMinor  Nilai buku aset bersih entitas target pada tanggal akuisisi.
     * @param  int  $tangibleFairValueAdjustmentMinor  Penyesuaian nilai wajar aset berwujud (properti, pabrik, persediaan).
     * @param  int  $identifiableIntangiblesMinor  Nilai wajar aset tak berwujud teridentifikasi (merek dagang, lisensi, paten).
     * @param  int  $deferredTaxLiabilityMinor  Liabilitas pajak tangguhan yang timbul dari selisih nilai wajar (22%).
     * @return array{
     *     purchase_consideration_minor: int,
     *     book_value_net_assets_minor: int,
     *     identifiable_net_assets_fair_value_minor: int,
     *     tangible_adjustment_minor: int,
     *     intangibles_fair_value_minor: int,
     *     deferred_tax_minor: int,
     *     goodwill_recognized_minor: int,
     *     is_bargain_purchase: bool
     * }
     */
    public static function executePurchasePriceAllocation(
        int $purchaseConsiderationMinor,
        int $bookValueNetAssetsMinor,
        int $tangibleFairValueAdjustmentMinor,
        int $identifiableIntangiblesMinor,
        int $deferredTaxLiabilityMinor
    ): array {
        if ($purchaseConsiderationMinor <= 0) {
            throw new InvalidArgumentException('Nilai imbalan akuisisi harus lebih besar dari nol.');
        }

        // Nilai Wajar Aset Bersih Teridentifikasi = Nilai Buku + Penyesuaian Berwujud + Aset Tak Berwujud - Pajak Tangguhan
        $identifiableNetAssetsFairValue = $bookValueNetAssetsMinor
            + $tangibleFairValueAdjustmentMinor
            + $identifiableIntangiblesMinor
            - $deferredTaxLiabilityMinor;

        $goodwill = $purchaseConsiderationMinor - $identifiableNetAssetsFairValue;
        $isBargainPurchase = ($goodwill < 0);

        return [
            'purchase_consideration_minor' => $purchaseConsiderationMinor,
            'book_value_net_assets_minor' => $bookValueNetAssetsMinor,
            'identifiable_net_assets_fair_value_minor' => $identifiableNetAssetsFairValue,
            'tangible_adjustment_minor' => $tangibleFairValueAdjustmentMinor,
            'intangibles_fair_value_minor' => $identifiableIntangiblesMinor,
            'deferred_tax_minor' => $deferredTaxLiabilityMinor,
            'goodwill_recognized_minor' => max(0, $goodwill),
            'is_bargain_purchase' => $isBargainPurchase,
        ];
    }

    /**
     * Menjalankan Uji Penurunan Nilai Goodwill Tahunan (Goodwill Impairment Test - PSAK 48 / IAS 36).
     *
     * @param  int  $carryingAmountCguMinor  Nilai tercatat Unit Penghasil Kas (CGU) termasuk goodwill.
     * @param  int  $projectedCashFlowYear1Minor  Arus kas proyeksi tahun ke-1.
     * @param  int  $projectedCashFlowYear2Minor  Arus kas proyeksi tahun ke-2.
     * @param  int  $projectedCashFlowYear3Minor  Arus kas proyeksi tahun ke-3.
     * @param  int  $terminalValueMinor  Nilai terminal pada akhir tahun proyeksi.
     * @param  int  $discountRateBps  Tingkat diskonto WACC dalam basis points (misal: 1050 untuk 10.50%).
     * @return array{
     *     carrying_amount_minor: int,
     *     recoverable_amount_minor: int,
     *     impairment_loss_minor: int,
     *     is_impaired: bool,
     *     status: string
     * }
     */
    public static function testGoodwillImpairment(
        int $carryingAmountCguMinor,
        int $projectedCashFlowYear1Minor,
        int $projectedCashFlowYear2Minor,
        int $projectedCashFlowYear3Minor,
        int $terminalValueMinor,
        int $discountRateBps
    ): array {
        if ($carryingAmountCguMinor <= 0 || $discountRateBps <= 0) {
            throw new InvalidArgumentException('Parameter pengujian impairment tidak valid.');
        }

        // Kalkulasi Present Value (PV) arus kas diskonto integer (10000 = basis pembagi 100%)
        // pv1 = cf1 / (1 + r)
        $factor1Bps = intdiv(10000 * 10000, 10000 + $discountRateBps);
        $pv1 = intdiv($projectedCashFlowYear1Minor * $factor1Bps, 10000);

        // pv2 = cf2 / (1 + r)^2
        $factor2Bps = intdiv($factor1Bps * 10000, 10000 + $discountRateBps);
        $pv2 = intdiv($projectedCashFlowYear2Minor * $factor2Bps, 10000);

        // pv3 = (cf3 + terminal) / (1 + r)^3
        $factor3Bps = intdiv($factor2Bps * 10000, 10000 + $discountRateBps);
        $pv3 = intdiv(($projectedCashFlowYear3Minor + $terminalValueMinor) * $factor3Bps, 10000);

        $recoverableAmount = $pv1 + $pv2 + $pv3;
        $impairmentLoss = max(0, $carryingAmountCguMinor - $recoverableAmount);
        $isImpaired = ($impairmentLoss > 0);

        return [
            'carrying_amount_minor' => $carryingAmountCguMinor,
            'recoverable_amount_minor' => $recoverableAmount,
            'impairment_loss_minor' => $impairmentLoss,
            'is_impaired' => $isImpaired,
            'status' => $isImpaired ? 'IMPAIRMENT_REQUIRED' : 'NO_IMPAIRMENT',
        ];
    }
}
