<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

use InvalidArgumentException;

/**
 * Menghitung liabilitas pajak/sertifikat penyesuaian perbatasan karbon Uni Eropa (EU CBAM)
 * berdasarkan Regulasi (EU) 2023/956.
 * Komputasi zero-float berakar integer kg, basis points, dan unit minor mata uang (EUR & IDR).
 */
final class CbamEmissionsTariff
{
    /**
     * @param  int  $productTonnageKg  Berat bersih komoditas ekspor kena CBAM (kg)
     * @param  int  $specificEmissionFactorKgCo2ePerTon  Intensitas emisi terpasang fasilitas produsen (kg CO2e / ton produk)
     * @param  int  $euBenchmarkFactorKgCo2ePerTon  Patokan emisi teknologi terbaik Uni Eropa (kg CO2e / ton produk)
     * @param  int  $freeAllowancePercentageBps  Alokasi kuota gratis masa transisi dalam basis points (misal 2000 = 20%)
     * @param  int  $carbonPriceEurMinorPerTon  Harga kuota lelang EU ETS per ton CO2e dalam sen EUR (misal 6500 = 65.00 EUR)
     * @param  int  $eurToIdrExchangeRateMinor  Nilai tukar 1 EUR ke IDR (misal 1720000 = Rp 17.200 per EUR)
     */
    public function calculate(
        int $productTonnageKg,
        int $specificEmissionFactorKgCo2ePerTon,
        int $euBenchmarkFactorKgCo2ePerTon,
        int $freeAllowancePercentageBps,
        int $carbonPriceEurMinorPerTon,
        int $eurToIdrExchangeRateMinor,
    ): CbamEmissionsTariffResult {
        if ($productTonnageKg <= 0) {
            throw new InvalidArgumentException('Tonase produk ekspor harus > 0 kg');
        }
        if ($specificEmissionFactorKgCo2ePerTon < 0 || $euBenchmarkFactorKgCo2ePerTon < 0) {
            throw new InvalidArgumentException('Faktor emisi tidak boleh negatif');
        }
        if ($carbonPriceEurMinorPerTon <= 0) {
            throw new InvalidArgumentException('Harga kuota karbon EU ETS harus > 0');
        }
        if ($eurToIdrExchangeRateMinor <= 0) {
            throw new InvalidArgumentException('Kurs EUR ke IDR harus > 0');
        }

        // 1. Total Emisi Tertanam Bruto (Embedded Emissions) dalam kg CO2e:
        // Tonase produk (ton) = productTonnageKg / 1000
        // Total emisi bruto kg CO2e = (productTonnageKg * specificEmissionFactor) / 1000
        $grossEmbeddedEmissionsKgCo2e = (int) intdiv(
            $productTonnageKg * $specificEmissionFactorKgCo2ePerTon,
            1000
        );

        // 2. Emisi Bersih Kena Beban (Net Taxable Emissions):
        // Jika fasilitas lebih bersih dari patokan benchmark EU, liabilitas = 0
        $emissionDifferencePerTon = max(0, $specificEmissionFactorKgCo2ePerTon - $euBenchmarkFactorKgCo2ePerTon);
        $taxableEmissionsKgCo2e = (int) intdiv(
            $productTonnageKg * $emissionDifferencePerTon,
            1000
        );

        // 3. Potongan Alokasi Kuota Bebas (Free Allocation Adjustment):
        $freeAllowanceKgCo2e = (int) intdiv(
            $taxableEmissionsKgCo2e * $freeAllowancePercentageBps,
            10000
        );
        $netPayableEmissionsKgCo2e = max(0, $taxableEmissionsKgCo2e - $freeAllowanceKgCo2e);

        // 4. Liabilitas CBAM dalam EUR sen (minor):
        // 1 ton = 1000 kg -> Liabilitas EUR minor = (netPayableEmissionsKgCo2e * carbonPriceEurMinorPerTon) / 1000
        $liabilityEurMinor = (int) intdiv(
            $netPayableEmissionsKgCo2e * $carbonPriceEurMinorPerTon,
            1000
        );

        // 5. Konversi Liabilitas ke IDR minor (rupiah):
        // Kurs EUR to IDR adalah dalam rupiah penuh (misal 17200) atau minor (misal 1720000 = Rp 17.200 per 100 sen EUR)
        // liabilityIdr = (liabilityEurMinor * eurToIdrExchangeRateMinor) / 100
        $liabilityIdr = (int) intdiv(
            $liabilityEurMinor * $eurToIdrExchangeRateMinor,
            100
        );

        // 6. Buat sertifikat verifikasi SHA-256
        $canonicalPayload = sprintf(
            'CBAM-TARIFF|TON:%d|SPEC:%d|BENCH:%d|FREE_BPS:%d|GROSS_KG:%d|NET_KG:%d|EUR_CENT:%d|IDR:%d',
            $productTonnageKg,
            $specificEmissionFactorKgCo2ePerTon,
            $euBenchmarkFactorKgCo2ePerTon,
            $freeAllowancePercentageBps,
            $grossEmbeddedEmissionsKgCo2e,
            $netPayableEmissionsKgCo2e,
            $liabilityEurMinor,
            $liabilityIdr
        );
        $certificateHash = hash('sha256', $canonicalPayload);

        return new CbamEmissionsTariffResult(
            productTonnageKg: $productTonnageKg,
            specificEmissionFactorKgCo2ePerTon: $specificEmissionFactorKgCo2ePerTon,
            euBenchmarkFactorKgCo2ePerTon: $euBenchmarkFactorKgCo2ePerTon,
            grossEmbeddedEmissionsKgCo2e: $grossEmbeddedEmissionsKgCo2e,
            netPayableEmissionsKgCo2e: $netPayableEmissionsKgCo2e,
            freeAllowanceDeductionKgCo2e: $freeAllowanceKgCo2e,
            liabilityEurMinor: $liabilityEurMinor,
            liabilityIdr: $liabilityIdr,
            certificateHash: $certificateHash,
            canonicalPayload: $canonicalPayload,
        );
    }
}
