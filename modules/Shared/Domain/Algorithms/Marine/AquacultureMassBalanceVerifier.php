<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

use DomainException;

/**
 * A15: AquacultureMassBalanceVerifier (ASC/MSC Chain of Custody Standard).
 *
 * Memverifikasi integritas neraca massa antara input biomassa panen hidup (live harvest biomass)
 * dengan output lot produk terolah (processed export lots) untuk mencegah pencampuran ikan/udang
 * ilegal atau non-sertifikasi ke dalam rantai pasok ekspor bersertifikat.
 */
class AquacultureMassBalanceVerifier
{
    // Batas rendemen maksimum teoritis berdasarkan bentuk produk olahan
    public const MAX_YIELD_HOSO = 0.98; // Head-On Shell-On: rendemen max 98% (hanya susut air)

    public const MAX_YIELD_HLSO = 0.68; // Headless Shell-On: rendemen max 68% (pembuangan kepala)

    public const MAX_YIELD_PDTO = 0.56; // Peeled Deveined Tail-On: rendemen max 56%

    public const MAX_YIELD_PND = 0.50; // Peeled and Deveined: rendemen max 50%

    public const MAX_YIELD_FISH_FILLET = 0.45; // Kerapu fillet: rendemen max 45%

    /**
     * Verifikasi kesesuaian neraca massa pemrosesan lot ekspor.
     *
     * @return array{
     *     is_compliant: bool,
     *     input_biomass_kg: float,
     *     output_product_kg: float,
     *     actual_yield_percentage: float,
     *     max_allowable_yield_percentage: float,
     *     unaccounted_surplus_kg: float,
     *     verdict: string,
     *     audit_message: string
     * }
     */
    public function verifyMassBalance(
        float $inputLiveBiomassKg,
        float $outputProcessedProductKg,
        string $productForm,
        float $glazeWaterPercentage = 8.0
    ): array {
        if ($inputLiveBiomassKg <= 0.0) {
            throw new DomainException('Biomassa input panen hidup harus lebih besar dari 0 kg.');
        }

        if ($outputProcessedProductKg < 0.0) {
            throw new DomainException('Biomassa produk olahan tidak boleh negatif.');
        }

        // Hitung berat bersih produk olahan tanpa lapisan es pelindung (net deglazed weight)
        $glazeFactor = max(0.0, min(25.0, $glazeWaterPercentage)) / 100.0;
        $netDeglazedProductKg = $outputProcessedProductKg * (1.0 - $glazeFactor);

        // Rendemen aktual dari biomassa hidup
        $actualYield = $netDeglazedProductKg / $inputLiveBiomassKg;
        $actualYieldPct = $actualYield * 100.0;

        $maxYield = match ($productForm) {
            'HEAD_ON_SHELL_ON', 'HOSO' => self::MAX_YIELD_HOSO,
            'HEADLESS_SHELL_ON', 'HLSO' => self::MAX_YIELD_HLSO,
            'PEELED_DEVEINED_TAIL_ON', 'PDTO' => self::MAX_YIELD_PDTO,
            'PEELED_AND_DEVEINED', 'PND', 'PEELED_DEVEINED' => self::MAX_YIELD_PND,
            'FILLET', 'FISH_FILLET' => self::MAX_YIELD_FISH_FILLET,
            default => 0.65,
        };

        $maxYieldPct = $maxYield * 100.0;
        $maxAllowableOutputKg = $inputLiveBiomassKg * $maxYield;

        // Jika output melebihi batas fisik biologis rendemen: indikasi kuat pemalsuan/pencampuran kargo luar
        $surplusKg = max(0.0, $netDeglazedProductKg - $maxAllowableOutputKg);
        $isCompliant = $surplusKg <= 0.01;

        $verdict = $isCompliant ? 'ASC_MSC_MASS_BALANCE_PASSED' : 'SUSPECTED_UNAUTHORIZED_BLENDING';

        $auditMessage = $isCompliant
            ? sprintf(
                'Neraca massa ASC/MSC sesuai: Rendemen aktual %.2f%% berada dalam batas standar %.2f%% untuk produk %s.',
                $actualYieldPct,
                $maxYieldPct,
                $productForm
            )
            : sprintf(
                'Pelanggaran neraca massa ASC/MSC: Rendemen %.2f%% melampaui batas maksimum %.2f%%. Terdeteksi surplus tak bertuan %.2f kg.',
                $actualYieldPct,
                $maxYieldPct,
                $surplusKg
            );

        return [
            'is_compliant' => $isCompliant,
            'input_biomass_kg' => round($inputLiveBiomassKg, 2),
            'output_product_kg' => round($outputProcessedProductKg, 2),
            'actual_yield_percentage' => round($actualYieldPct, 2),
            'max_allowable_yield_percentage' => round($maxYieldPct, 2),
            'unaccounted_surplus_kg' => round($surplusKg, 2),
            'verdict' => $verdict,
            'audit_message' => $auditMessage,
        ];
    }
}
