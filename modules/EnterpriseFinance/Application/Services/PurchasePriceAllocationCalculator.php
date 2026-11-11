<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Kalkulator Alokasi Harga Beli Akuisisi (Purchase Price Allocation - PPA) (54.8 - PSAK 22 / IFRS 3).
 * Mengalokasikan imbalan yang dialihkan (Purchase Consideration) ke:
 * 1. Nilai Wajar Aset Berwujud Teridentifikasi (Tanah, Pabrik, Mesin, Kas).
 * 2. Nilai Wajar Aset Tak Berwujud Teridentifikasi (Hak Paten, Merek Dagang, Kontrak Pelanggan).
 * 3. Nilai Wajar Liabilitas yang Diambil Alih (Utang Bank, Kewajiban Kontinjensi).
 * 4. Saldo Goodwill Positif (jika Imbalan > Aset Bersih Wajar) atau Keuntungan Pembelian dengan Diskon (Bargain Purchase).
 */
class PurchasePriceAllocationCalculator
{
    /**
     * Menghitung alokasi PPA dan saldo Goodwill perolehan akuisisi.
     *
     * @param  list<array{name: string, fair_value_minor: int}>  $tangibleAssets
     * @param  list<array{name: string, fair_value_minor: int}>  $intangibleAssets
     * @param  list<array{name: string, fair_value_minor: int}>  $assumedLiabilities
     * @return array{
     *     acquisition_code: string,
     *     target_entity_name: string,
     *     purchase_price_minor: int,
     *     total_tangible_assets_minor: int,
     *     total_intangible_assets_minor: int,
     *     total_assumed_liabilities_minor: int,
     *     fair_value_net_identifiable_assets_minor: int,
     *     goodwill_recognized_minor: int,
     *     bargain_purchase_gain_minor: int,
     *     is_goodwill: bool
     * }
     */
    public function allocatePurchasePrice(
        string $acquisitionCode,
        string $targetEntityName,
        int $purchasePriceMinor,
        array $tangibleAssets,
        array $intangibleAssets,
        array $assumedLiabilities
    ): array {
        if ($purchasePriceMinor <= 0) {
            throw new InvalidArgumentException('Nilai transaksi imbalan pembelian akuisisi harus bernilai positif.');
        }

        $totalTangible = 0;
        foreach ($tangibleAssets as $ta) {
            $val = (int) $ta['fair_value_minor'];
            if ($val < 0) {
                throw new InvalidArgumentException('Nilai wajar aset tidak boleh bernilai negatif.');
            }
            $totalTangible += $val;
        }

        $totalIntangible = 0;
        foreach ($intangibleAssets as $ia) {
            $val = (int) $ia['fair_value_minor'];
            if ($val < 0) {
                throw new InvalidArgumentException('Nilai wajar aset tak berwujud tidak boleh bernilai negatif.');
            }
            $totalIntangible += $val;
        }

        $totalLiab = 0;
        foreach ($assumedLiabilities as $al) {
            $val = (int) $al['fair_value_minor'];
            if ($val < 0) {
                throw new InvalidArgumentException('Nilai wajar liabilitas tidak boleh bernilai negatif.');
            }
            $totalLiab += $val;
        }

        // Nilai Wajar Aset Bersih Teridentifikasi = (Aset Berwujud + Tak Berwujud) - Liabilitas
        $netIdentifiableAssets = ($totalTangible + $totalIntangible) - $totalLiab;

        // Goodwill = Harga Beli - Nilai Wajar Aset Bersih Teridentifikasi
        $diff = $purchasePriceMinor - $netIdentifiableAssets;

        $goodwill = max(0, $diff);
        $bargainGain = ($diff < 0) ? abs($diff) : 0;

        return [
            'acquisition_code' => $acquisitionCode,
            'target_entity_name' => $targetEntityName,
            'purchase_price_minor' => $purchasePriceMinor,
            'total_tangible_assets_minor' => $totalTangible,
            'total_intangible_assets_minor' => $totalIntangible,
            'total_assumed_liabilities_minor' => $totalLiab,
            'fair_value_net_identifiable_assets_minor' => $netIdentifiableAssets,
            'goodwill_recognized_minor' => $goodwill,
            'bargain_purchase_gain_minor' => $bargainGain,
            'is_goodwill' => ($goodwill > 0),
        ];
    }
}
