<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Mesin Penanganan Klaim Kasus Katastropik & Biaya Tinggi (CatastrophicCaseClaimEngine).
 * Mengelola pembiayaan kasus berisiko tinggi (*High-Cost High-Volume Catastrophic Cases*):
 *   1. Pembedahan Jantung Terbuka & Bedah Vaskular Mayor (CABG, Valve Replacement).
 *   2. Perawatan Kritis ICU/ICCU Berkepanjangan dengan Ventilasi Mekanik > 96 Jam.
 *   3. Penyakit Ginjal Kronis Terminal dengan Sesi Hemodialisis Rutin & Komplikasi Vaskular.
 *   4. Skema Reasuransi Stop-Loss Internal (Hospital Stop-Loss Threshold Reinsurance).
 */
class CatastrophicCaseClaimEngine
{
    /**
     * Ambang batas kerugian stop-loss internal rumah sakit per episode (Rp 75.000.000).
     */
    public const HOSPITAL_STOP_LOSS_THRESHOLD_IDR = 75_000_000;

    /**
     * Peta koefisien bobot katastropik kasus medis.
     *
     * @var array<string, array{name: string, complexity_weight: float, expected_icu_days: int}>
     */
    private const CATASTROPHIC_DIAGNOSES = [
        'CABG_SURGERY' => ['name' => 'Operasi Bypass Arteri Koroner Jantung', 'complexity_weight' => 2.85, 'expected_icu_days' => 4],
        'VALVE_REPLACEMENT' => ['name' => 'Penggantian Katup Jantung Mekanik/Biologis', 'complexity_weight' => 2.65, 'expected_icu_days' => 5],
        'CRANIOTOMY_TUMOR' => ['name' => 'Kraniotomi Pengangkatan Tumor Otak', 'complexity_weight' => 2.45, 'expected_icu_days' => 4],
        'EXTENSIVE_BURN_MODS' => ['name' => 'Luka Bakar Luas Derajat III dengan Syok Sepsis', 'complexity_weight' => 3.20, 'expected_icu_days' => 14],
        'ORGAN_TRANSPLANT' => ['name' => 'Transplantasi Ginjal & Imunosupresi', 'complexity_weight' => 3.50, 'expected_icu_days' => 7],
    ];

    /**
     * Mengevaluasi pembiayaan kasus katastropik dan menghitung proteksi stop-loss.
     *
     * @param  string  $caseType  Kategori kasus katastropik
     * @param  int  $totalHospitalCostIdr  Total biaya pengeluaran riil rumah sakit
     * @param  int  $totalPayerRecoveredIdr  Total pembayaran yang diterima dari BPJS dan asuransi
     * @param  int  $actualIcuDays  Jumlah hari perawatan di ICU
     * @return array{
     *     case_type: string,
     *     case_name: string,
     *     complexity_weight: float,
     *     total_hospital_cost_idr: int,
     *     total_payer_recovered_idr: int,
     *     net_hospital_deficit_idr: int,
     *     is_stop_loss_triggered: bool,
     *     stop_loss_reinsurance_claim_idr: int,
     *     uncovered_hospital_loss_idr: int
     * }
     */
    public function evaluateCatastrophicFinancials(
        string $caseType,
        int $totalHospitalCostIdr,
        int $totalPayerRecoveredIdr,
        int $actualIcuDays
    ): array {
        $type = strtoupper(trim($caseType));
        $meta = self::CATASTROPHIC_DIAGNOSES[$type] ?? [
            'name' => 'Kasus Katastropik Lainnya',
            'complexity_weight' => 2.0,
            'expected_icu_days' => 3,
        ];

        if ($totalHospitalCostIdr <= 0) {
            throw new DomainException('Total biaya rumah sakit harus lebih besar dari nol.');
        }

        // Defisit rumah sakit = Biaya riil - Total yang diganti penjamin
        $netDeficit = max(0, $totalHospitalCostIdr - $totalPayerRecoveredIdr);

        // Jika defisit melebihi ambang stop-loss (Rp 75jt), sisa defisit dapat diklaimkan ke reasuransi stop-loss rumah sakit
        $isStopLossTriggered = $netDeficit > self::HOSPITAL_STOP_LOSS_THRESHOLD_IDR;
        $reinsuranceClaim = 0;
        $uncoveredLoss = $netDeficit;

        if ($isStopLossTriggered) {
            $excessDeficit = $netDeficit - self::HOSPITAL_STOP_LOSS_THRESHOLD_IDR;
            // Reasuransi stop-loss menanggung 85% dari defisit di atas ambang
            $reinsuranceClaim = (int) round($excessDeficit * 0.85);
            $uncoveredLoss = $netDeficit - $reinsuranceClaim;
        }

        return [
            'case_type' => $type,
            'case_name' => $meta['name'],
            'complexity_weight' => $meta['complexity_weight'],
            'total_hospital_cost_idr' => $totalHospitalCostIdr,
            'total_payer_recovered_idr' => $totalPayerRecoveredIdr,
            'net_hospital_deficit_idr' => $netDeficit,
            'is_stop_loss_triggered' => $isStopLossTriggered,
            'stop_loss_reinsurance_claim_idr' => $reinsuranceClaim,
            'uncovered_hospital_loss_idr' => $uncoveredLoss,
        ];
    }
}
