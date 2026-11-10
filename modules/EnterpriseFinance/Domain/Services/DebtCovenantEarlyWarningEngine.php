<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantResultDTO;

/**
 * Mesin Deteksi Dini Pelanggaran Covenant Utang Sindikasi (54.7 & D7).
 * Menghitung jarak penyangga keamanan (safety buffer distance) antara rasio aktual
 * dengan ambang batas batas covenant kontrak perjanjian kredit sindikasi:
 * - DSCR Threshold: minimum 1.250‰ (1.25x)
 * - DER Threshold: maksimum 2.500‰ (2.50x)
 * - ISCR Threshold: minimum 2.000‰ (2.00x)
 * - Current Ratio Threshold: minimum 1.100‰ (1.10x)
 * Mengklasifikasikan tingkat ancaman: GREEN (Aman), AMBER (Peringatan Dini), RED (Pelanggaran).
 */
class DebtCovenantEarlyWarningEngine
{
    /**
     * Ambang batas aman standar perjanjian kredit sindikasi.
     */
    public const DSCR_MIN_PERMILLE = 1250;

    public const DER_MAX_PERMILLE = 2500;

    public const ISCR_MIN_PERMILLE = 2000;

    public const CURRENT_RATIO_MIN_PERMILLE = 1100;

    /**
     * Menganalisis jarak penyangga keamanan seluruh rasio covenant.
     *
     * @return array{
     *     facility_code: string,
     *     overall_threat_level: string,
     *     dscr_buffer_permille: int,
     *     der_buffer_permille: int,
     *     iscr_buffer_permille: int,
     *     current_ratio_buffer_permille: int,
     *     recommendations: list<string>
     * }
     */
    public function assessEarlyWarning(SyndicatedLoanCovenantResultDTO $covenantResult): array
    {
        $dscr = $covenantResult->dscrPermille;
        $der = $covenantResult->derPermille;
        $iscr = $covenantResult->iscrPermille;
        $cr = $covenantResult->currentRatioPermille;

        // Buffer: Jarak ke batas aman (positif = aman, negatif = breach)
        $dscrBuffer = $dscr - self::DSCR_MIN_PERMILLE;
        $derBuffer = self::DER_MAX_PERMILLE - $der;
        $iscrBuffer = $iscr - self::ISCR_MIN_PERMILLE;
        $crBuffer = $cr - self::CURRENT_RATIO_MIN_PERMILLE;

        $hasBreach = ($dscrBuffer < 0 || $derBuffer < 0 || $iscrBuffer < 0 || $crBuffer < 0);
        $hasAmber = ($dscrBuffer < 150 || $derBuffer < 200 || $iscrBuffer < 250 || $crBuffer < 100);

        $threatLevel = $hasBreach ? 'RED_BREACH' : ($hasAmber ? 'AMBER_WARNING' : 'GREEN_SAFE');

        $recommendations = [];
        if ($dscrBuffer < 0) {
            $recommendations[] = "DSCR berada di bawah batas minimum ({$dscr}‰ < 1250‰). Segera restrukturisasi jadwal cicilan pokok atau suntik likuiditas operasional.";
        } elseif ($dscrBuffer < 150) {
            $recommendations[] = "Penyangga DSCR menipis ({$dscrBuffer}‰ di atas ambang). Batasi pengeluaran kas non-esensial.";
        }

        if ($derBuffer < 0) {
            $recommendations[] = "DER melampaui batas maksimum ({$der}‰ > 2500‰). Dilarang menambah penarikan utang baru; tingkatkan ekuitas atau tahan dividen.";
        } elseif ($derBuffer < 200) {
            $recommendations[] = "Penyangga DER mendekati batas kritis ({$derBuffer}‰ tersisa). Evaluasi rencana belanja modal berutang.";
        }

        if ($iscrBuffer < 0) {
            $recommendations[] = "ISCR tidak mencukupi untuk menutup beban bunga ({$iscr}‰ < 2000‰). Pertimbangkan lindung nilai suku bunga (IRS).";
        }

        if ($crBuffer < 0) {
            $recommendations[] = "Current Ratio di bawah batas solvabilitas jangka pendek ({$cr}‰ < 1100‰). Percepat penagihan piutang dagang dan perlambat pembayaran vendor.";
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Seluruh rasio covenant berada dalam zona hijau dengan penyangga keamanan memadai.';
        }

        return [
            'facility_code' => $covenantResult->facilityCode,
            'overall_threat_level' => $threatLevel,
            'dscr_buffer_permille' => $dscrBuffer,
            'der_buffer_permille' => $derBuffer,
            'iscr_buffer_permille' => $iscrBuffer,
            'current_ratio_buffer_permille' => $crBuffer,
            'recommendations' => $recommendations,
        ];
    }
}
