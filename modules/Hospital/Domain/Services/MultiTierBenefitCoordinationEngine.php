<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Mesin Koordinasi Manfaat Multi-Tingkat (MultiTierBenefitCoordinationEngine).
 * Menghitung pembagian tanggungan asuransi kesehatan berdasarkan sub-limit benefit:
 *   1. Kamar & Akomodasi (Room & Board Allowance)
 *   2. Perawatan Intensif (ICU / ICCU Daily Limit)
 *   3. Pembedahan & Kamar Operasi (Surgical Fees)
 *   4. Visite & Konsultasi Dokter Spesialis (Doctor Attendance)
 *   5. Obat-obatan & Farmasi Rawat Inap (Inpatient Prescription Drugs)
 *   6. Uji Diagnostik Laboratorium & Patologi (Clinical Pathology & Tests)
 *   7. Radiologi & Diagnostik Imaging (Imaging & PACS)
 *   8. Alat Kesehatan Habis Pakai & Implan (Medical Consumables & Implants)
 */
class MultiTierBenefitCoordinationEngine
{
    /**
     * Peta batas sub-limit standar polis asuransi komersial (Plan Platinum).
     *
     * @var array<string, array{daily_limit_idr: int, max_days: int, max_per_episode_idr: int, coinsurance_pct: float}>
     */
    private const POLICY_BENEFIT_SCHEDULE = [
        'BED_DAY' => ['daily_limit_idr' => 1_500_000, 'max_days' => 60, 'max_per_episode_idr' => 90_000_000, 'coinsurance_pct' => 0.0],
        'ICU_DAY' => ['daily_limit_idr' => 3_500_000, 'max_days' => 30, 'max_per_episode_idr' => 105_000_000, 'coinsurance_pct' => 0.0],
        'PROCEDURE' => ['daily_limit_idr' => 50_000_000, 'max_days' => 1, 'max_per_episode_idr' => 75_000_000, 'coinsurance_pct' => 0.10],
        'DOCTOR_FEE' => ['daily_limit_idr' => 750_000, 'max_days' => 60, 'max_per_episode_idr' => 45_000_000, 'coinsurance_pct' => 0.0],
        'PHARMACY' => ['daily_limit_idr' => 35_000_000, 'max_days' => 60, 'max_per_episode_idr' => 50_000_000, 'coinsurance_pct' => 0.05],
        'LAB' => ['daily_limit_idr' => 10_000_000, 'max_days' => 60, 'max_per_episode_idr' => 20_000_000, 'coinsurance_pct' => 0.0],
        'RADIOLOGY' => ['daily_limit_idr' => 15_000_000, 'max_days' => 60, 'max_per_episode_idr' => 25_000_000, 'coinsurance_pct' => 0.0],
        'CONSUMABLES' => ['daily_limit_idr' => 10_000_000, 'max_days' => 60, 'max_per_episode_idr' => 15_000_000, 'coinsurance_pct' => 0.15],
    ];

    /**
     * Mengevaluasi rincian tagihan terhadap jadwal polis asuransi swasta.
     *
     * @param  array<int, array{category: string, subtotal_idr: int, quantity: int}>  $itemizedCharges
     * @param  int  $bpjsPrimaryContributionIdr  Jumlah yang sudah dilunasi BPJS
     * @param  int  $deductibleIdr  Jumlah deductible tahunan polis
     * @return array<string, mixed>
     */
    public function adjudicatePolicySublimits(
        array $itemizedCharges,
        int $bpjsPrimaryContributionIdr,
        int $deductibleIdr = 0
    ): array {
        $totalGross = 0;
        $categoryBreakdown = [];
        $totalInsurerCovered = 0;
        $totalCoinsuranceDeduction = 0;
        $totalSublimitExcess = 0;

        foreach ($itemizedCharges as $item) {
            $cat = strtoupper($item['category']);
            $cost = max(0, (int) $item['subtotal_idr']);
            $qty = max(1, (int) $item['quantity']);
            $totalGross += $cost;

            $schedule = self::POLICY_BENEFIT_SCHEDULE[$cat] ?? [
                'daily_limit_idr' => 5_000_000,
                'max_days' => 30,
                'max_per_episode_idr' => 20_000_000,
                'coinsurance_pct' => 0.20,
            ];

            // Cek batas harian atau per episode
            $maxAllowedPerDay = $schedule['daily_limit_idr'] * $qty;
            $cappedAllowed = min($cost, $schedule['max_per_episode_idr'], $maxAllowedPerDay);
            $excessAmount = max(0, $cost - $cappedAllowed);

            // Hitung potongan co-insurance
            $coinsurance = (int) round($cappedAllowed * $schedule['coinsurance_pct']);
            $netCovered = max(0, $cappedAllowed - $coinsurance);

            $totalInsurerCovered += $netCovered;
            $totalCoinsuranceDeduction += $coinsurance;
            $totalSublimitExcess += $excessAmount;

            $categoryBreakdown[$cat] = [
                'billed_idr' => $cost,
                'sublimit_capped_idr' => $cappedAllowed,
                'sublimit_excess_idr' => $excessAmount,
                'coinsurance_idr' => $coinsurance,
                'net_insurer_covered_idr' => $netCovered,
            ];
        }

        // Koordinasi dengan BPJS: Pengurangan klaim primer
        // Asuransi swasta hanya membayar selisih setelah dikurangi klaim BPJS
        $eligibleForPrivateInsurer = max(0, $totalInsurerCovered - $bpjsPrimaryContributionIdr);

        // Penerapan Deductible Polis
        $deductibleApplied = min($eligibleForPrivateInsurer, max(0, $deductibleIdr));
        $finalPrivateInsurerPayable = $eligibleForPrivateInsurer - $deductibleApplied;

        // Tanggungan Pasien = Excess sublimit + Coinsurance + Deductible + Sisa non-covered
        $patientOutOfPocket = ($totalGross - $bpjsPrimaryContributionIdr) - $finalPrivateInsurerPayable;

        return [
            'total_gross_billed_idr' => $totalGross,
            'bpjs_primary_settled_idr' => $bpjsPrimaryContributionIdr,
            'private_insurer_eligible_idr' => $eligibleForPrivateInsurer,
            'deductible_applied_idr' => $deductibleApplied,
            'final_private_insurer_payable_idr' => $finalPrivateInsurerPayable,
            'patient_out_of_pocket_idr' => max(0, $patientOutOfPocket),
            'total_sublimit_excess_idr' => $totalSublimitExcess,
            'total_coinsurance_idr' => $totalCoinsuranceDeduction,
            'category_breakdown' => $categoryBreakdown,
        ];
    }
}
