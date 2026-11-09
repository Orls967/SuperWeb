<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Layanan Domain Master Tarif & Regionalisasi Pelayanan Rumah Sakit (HospitalTariffMasterService).
 * Mengelola penyesuaian tarif dinamis berbasis:
 *   1. Regionalisasi Tarif BPJS (Regional 1 Jawa/Bali s/d Regional 5 Papua/Maluku).
 *   2. Skema Diskon Kemitraan Korporat & Asuransi Swasta Terakreditasi.
 *   3. Pengendalian Versi Tarif Rumah Sakit (Versioning & Historical Tracking).
 *   4. Alokasi Subsidi Silang Pasien Kurang Mampu (Hospital Charity / Social Relief Fund).
 */
class HospitalTariffMasterService
{
    /**
     * Koefisien pengali regional tarif nasional BPJS Kesehatan (Permenkes RI).
     *
     * @var array<int, float>
     */
    private const REGIONAL_TARIFF_MULTIPLIERS = [
        1 => 1.00, // Regional 1: Jawa, Bali, DIY
        2 => 1.05, // Regional 2: Sumatera Bagian Selatan & Barat
        3 => 1.12, // Regional 3: Kalimantan & Sulawesi
        4 => 1.25, // Regional 4: Nusa Tenggara & Maluku
        5 => 1.35, // Regional 5: Papua & Papua Barat
    ];

    /**
     * Peta diskon kemitraan korporat terdaftar.
     *
     * @var array<string, array{discount_pct: float, max_discount_idr: int, applies_to_pharmacy: bool}>
     */
    private const CORPORATE_PARTNERSHIP_TIERS = [
        'TIER_PLATINUM_INSURER' => ['discount_pct' => 15.0, 'max_discount_idr' => 10_000_000, 'applies_to_pharmacy' => false],
        'TIER_GOLD_CORPORATE' => ['discount_pct' => 10.0, 'max_discount_idr' => 5_000_000, 'applies_to_pharmacy' => false],
        'TIER_SILVER_PARTNER' => ['discount_pct' => 5.0, 'max_discount_idr' => 2_500_000, 'applies_to_pharmacy' => false],
        'COMMUNITY_HEALTH_CHARITY' => ['discount_pct' => 35.0, 'max_discount_idr' => 25_000_000, 'applies_to_pharmacy' => true],
    ];

    /**
     * Menghitung penyesuaian tarif berdasarkan regionalisasi wilayah geografis rumah sakit.
     *
     * @param  int  $baseNationalTariffIdr  Tarif dasar standar nasional
     * @param  int  $regionalZoneCode  Kode regional (1 - 5)
     * @return int Tarif setelah disesuaikan dengan koefisien wilayah
     */
    public function adjustTariffForRegionalZone(int $baseNationalTariffIdr, int $regionalZoneCode): int
    {
        if ($baseNationalTariffIdr <= 0) {
            throw new DomainException('Tarif dasar harus lebih besar dari nol.');
        }

        $multiplier = self::REGIONAL_TARIFF_MULTIPLIERS[$regionalZoneCode] ?? 1.00;

        return (int) round($baseNationalTariffIdr * $multiplier);
    }

    /**
     * Menghitung diskon atau subsidi kemitraan terhadap tagihan bruto.
     *
     * @param  string  $partnershipTier  Kategori tingkatan kemitraan (e.g. TIER_PLATINUM_INSURER)
     * @param  int  $grossMedicalChargesIdr  Total tagihan tindakan & akomodasi
     * @param  int  $pharmacyChargesIdr  Total tagihan farmasi obat
     * @return array{
     *     tier_code: string,
     *     applied_discount_idr: int,
     *     net_payable_charges_idr: int,
     *     is_charity_subsidized: bool
     * }
     */
    public function applyCorporatePartnershipDiscount(
        string $partnershipTier,
        int $grossMedicalChargesIdr,
        int $pharmacyChargesIdr
    ): array {
        $tier = strtoupper(trim($partnershipTier));
        $config = self::CORPORATE_PARTNERSHIP_TIERS[$tier] ?? [
            'discount_pct' => 0.0,
            'max_discount_idr' => 0,
            'applies_to_pharmacy' => false,
        ];

        $eligibleBase = $grossMedicalChargesIdr;
        if ($config['applies_to_pharmacy']) {
            $eligibleBase += $pharmacyChargesIdr;
        }

        $calculatedDiscount = (int) round($eligibleBase * ($config['discount_pct'] / 100));
        $appliedDiscount = min($calculatedDiscount, $config['max_discount_idr']);

        $totalGross = $grossMedicalChargesIdr + $pharmacyChargesIdr;
        $netPayable = max(0, $totalGross - $appliedDiscount);

        return [
            'tier_code' => $tier,
            'applied_discount_idr' => $appliedDiscount,
            'net_payable_charges_idr' => $netPayable,
            'is_charity_subsidized' => $tier === 'COMMUNITY_HEALTH_CHARITY',
        ];
    }
}
