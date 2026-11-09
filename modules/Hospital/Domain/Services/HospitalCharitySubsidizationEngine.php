<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;
use Modules\Hospital\Domain\ValueObjects\HospitalMoney;

/**
 * HospitalCharitySubsidizationEngine
 *
 * Mengelola program bantuan pembiayaan sosial, zakat/infaq rumah sakit,
 * dan subsidi silang bagi pasien dhuafa/kurang mampu (Permenkes No. 4/2018).
 * Menghitung persentase subsidi, memvalidasi verifikasi berkas SKTM,
 * dan memetakan alokasi biaya ke contra-revenue / rekening charity.
 */
class HospitalCharitySubsidizationEngine
{
    public const TIER_FULL_SUBSIDY = 'FULL_SUBSIDY_100';

    public const TIER_MAJOR_SUBSIDY = 'MAJOR_SUBSIDY_75';

    public const TIER_MODERATE_SUBSIDY = 'MODERATE_SUBSIDY_50';

    public const TIER_PARTIAL_SUBSIDY = 'PARTIAL_SUBSIDY_25';

    public const TIER_REJECTED = 'SUBSIDY_REJECTED';

    /**
     * Evaluasi kelayakan subsidi amal dan hitung komponen bantuan.
     *
     * @param  HospitalMoney  $totalBill  Tagihan kotor pasien yang belum tertanggung
     * @param  int  $verifiedMonthlyIncome  Income bulanan kepala keluarga (IDR)
     * @param  int  $dependentsCount  Jumlah tanggungan keluarga
     * @param  bool  $hasValidSktm  Memiliki SKTM resmi dari kelurahan
     * @param  bool  $isEmergencyLifeThreatening  Kasus gawat darurat mengancam jiwa
     * @param  int  $availableCharityFundBalance  Saldo dana charity RS yang tersedia (IDR)
     * @return array{
     *     tier: string,
     *     subsidy_ratio: float,
     *     subsidized_amount: HospitalMoney,
     *     patient_copay: HospitalMoney,
     *     fund_drawdown_amount: int,
     *     funding_source: string,
     *     approval_required_role: string,
     *     notes: string
     * }
     */
    public function evaluateCharitySubsidy(
        HospitalMoney $totalBill,
        int $verifiedMonthlyIncome,
        int $dependentsCount,
        bool $hasValidSktm,
        bool $isEmergencyLifeThreatening,
        int $availableCharityFundBalance
    ): array {
        if ($totalBill->amountInMinor <= 0) {
            throw new InvalidArgumentException('Total tagihan harus lebih besar dari 0');
        }
        if ($dependentsCount < 0) {
            throw new InvalidArgumentException('Jumlah tanggungan tidak boleh negatif');
        }

        // Per capita income calculation: income bulanan dibagi (tanggungan + 1)
        $effectiveDependents = max(1, $dependentsCount + 1);
        $perCapitaIncome = (int) floor($verifiedMonthlyIncome / $effectiveDependents);

        // Garis kemiskinan rujukan BPS perkotaan (approx Rp 600.000 / kapita / bulan)
        $extremePovertyThreshold = 400000;
        $moderatePovertyThreshold = 800000;
        $vulnerableThreshold = 1500000;

        $tier = self::TIER_REJECTED;
        $subsidyRatio = 0.0;
        $notes = 'Pasien tidak memenuhi kriteria penerima subsidi amal RS.';

        if ($isEmergencyLifeThreatening) {
            // Pasien darurat jiwa selalu dilindungi subsidi darurat minimal 50% jika keluarga rentan
            if ($perCapitaIncome <= $vulnerableThreshold || $hasValidSktm) {
                $tier = self::TIER_FULL_SUBSIDY;
                $subsidyRatio = 1.0;
                $notes = 'Bantuan darurat penyelamatan jiwa (life-saving emergency protocol).';
            } else {
                $tier = self::TIER_MODERATE_SUBSIDY;
                $subsidyRatio = 0.50;
                $notes = 'Subsidi darurat parsial penjaminan awal ICU/IGD.';
            }
        } elseif ($hasValidSktm) {
            if ($perCapitaIncome <= $extremePovertyThreshold) {
                $tier = self::TIER_FULL_SUBSIDY;
                $subsidyRatio = 1.0;
                $notes = 'SKTM terverifikasi & kemiskinan ekstrem: Bebas biaya penuh melalui dana zakat/CSR.';
            } elseif ($perCapitaIncome <= $moderatePovertyThreshold) {
                $tier = self::TIER_MAJOR_SUBSIDY;
                $subsidyRatio = 0.75;
                $notes = 'SKTM terverifikasi & keluarga pra-sejahtera: Subsidi 75%.';
            } elseif ($perCapitaIncome <= $vulnerableThreshold) {
                $tier = self::TIER_MODERATE_SUBSIDY;
                $subsidyRatio = 0.50;
                $notes = 'SKTM terverifikasi & keluarga rentan: Subsidi 50%.';
            } else {
                $tier = self::TIER_PARTIAL_SUBSIDY;
                $subsidyRatio = 0.25;
                $notes = 'SKTM terverifikasi dengan pendapatan batas atas: Subsidi 25%.';
            }
        } elseif ($perCapitaIncome <= $extremePovertyThreshold) {
            // Tanpa SKTM tetapi kondisi riil ekstrem
            $tier = self::TIER_MODERATE_SUBSIDY;
            $subsidyRatio = 0.50;
            $notes = 'Asesmen tim sosial medis: Bantuan sementara menunggu kelengkapan SKTM.';
        }

        // Hitung nominal subsidi
        $subsidizedCents = (int) round($totalBill->amountInMinor * $subsidyRatio);
        $patientCopayCents = $totalBill->amountInMinor - $subsidizedCents;

        // Cek kecukupan saldo dana charity
        $fundingSource = 'HOSPITAL_ZAKAT_CSR_FUND';
        $subsidizedInIdr = (int) ceil($subsidizedCents / 100);

        if ($subsidizedInIdr > $availableCharityFundBalance) {
            // Jika dana CSR/Zakat tidak cukup, alokasikan sisa ke cadangan diskon direksi
            $fundingSource = 'HOSPITAL_BOARD_DISCRETIONARY_DISCOUNT';
        }

        // Tentukan otoritas pemberi persetujuan
        $approvalRole = 'HEAD_OF_MEDICAL_SOCIAL_WORK';
        if ($subsidizedCents > 1500000000) { // > Rp 15.000.000
            $approvalRole = 'PRESIDENT_DIRECTOR';
        } elseif ($subsidizedCents > 500000000) { // > Rp 5.000.000
            $approvalRole = 'DIRECTOR_OF_FINANCE';
        }

        return [
            'tier' => $tier,
            'subsidy_ratio' => $subsidyRatio,
            'subsidized_amount' => new HospitalMoney($subsidizedCents, $totalBill->currency),
            'patient_copay' => new HospitalMoney($patientCopayCents, $totalBill->currency),
            'fund_drawdown_amount' => min($subsidizedInIdr, $availableCharityFundBalance),
            'funding_source' => $fundingSource,
            'approval_required_role' => $approvalRole,
            'notes' => $notes,
        ];
    }
}
