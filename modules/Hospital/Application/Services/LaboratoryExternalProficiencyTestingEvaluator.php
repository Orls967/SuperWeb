<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Evaluator Pemantapan Mutu Eksternal (PME / EQAS - External Quality Assessment Scheme) (Fase 105).
 *
 * Menerapkan standar evaluasi uji profisiensi laboratorium internasional (ISO/IEC 17043):
 * 1. Standard Deviation Index (SDI / Z-Score):
 *    SDI = (Hasil Laboratorium - Rerata Peer Group) / Standar Deviasi Peer Group
 * 2. Persentase Deviasi (% Difference from Target):
 *    % Dev = ((Hasil - Target) / Target) * 100%
 * 3. Kategori Kinerja SDI:
 *    - |SDI| <= 1.0  : EXCELLENT (kinerja analitik sangat prima)
 *    - 1.0 < |SDI| <= 2.0 : SATISFACTORY (kinerja dapat diterima)
 *    - 2.0 < |SDI| < 3.0  : QUESTIONABLE / WARNING (peringatan, perlu investigasi)
 *    - |SDI| >= 3.0       : UNSATISFACTORY / ACTION REQUIRED (tindakan korektif wajib)
 * 4. Analisis Diagram Youden (Youden Plot 2-Sample):
 *    Mendeteksi galat analitik sistematik (keduanya deviasi pada kuadran I atau III)
 *    vs galat acak / random error (kuadran II atau IV).
 */
class LaboratoryExternalProficiencyTestingEvaluator
{
    /**
     * Hitung Standard Deviation Index (SDI) dan evaluasi performa analitik.
     *
     * @param  string  $analyteCode  Kode analit atau parameter uji
     * @param  float  $labResult  Hasil pengukuran laboratorium sendiri
     * @param  float  $peerGroupMean  Nilai rerata konsensus peer group (metode/reagen sejenis)
     * @param  float  $peerGroupSd  Standar deviasi peer group
     * @return array{
     *     analyte: string,
     *     lab_result: float,
     *     peer_group_mean: float,
     *     peer_group_sd: float,
     *     sdi: float,
     *     percent_difference: float,
     *     performance_category: string,
     *     is_acceptable: bool,
     *     requires_corrective_action: bool,
     *     evaluation_notes: string
     * }
     */
    public function evaluateSdi(
        string $analyteCode,
        float $labResult,
        float $peerGroupMean,
        float $peerGroupSd
    ): array {
        if ($peerGroupSd <= 0.0) {
            throw new InvalidArgumentException('Standar deviasi peer group harus lebih besar dari 0.');
        }

        if ($peerGroupMean <= 0.0) {
            throw new InvalidArgumentException('Rerata peer group harus lebih besar dari 0.');
        }

        $sdi = round(($labResult - $peerGroupMean) / $peerGroupSd, 2);
        $absSdi = abs($sdi);

        $percentDiff = round((($labResult - $peerGroupMean) / $peerGroupMean) * 100.0, 2);

        $performance = 'EXCELLENT';
        $isAcceptable = true;
        $actionRequired = false;
        $notes = 'Kinerja analitik laboratorium sangat akurat mendekati konsensus peer.';

        if ($absSdi > 1.0 && $absSdi <= 2.0) {
            $performance = 'SATISFACTORY';
            $isAcceptable = true;
            $actionRequired = false;
            $notes = 'Kinerja analitik dalam batas toleransi variabilitas yang dapat diterima.';
        } elseif ($absSdi > 2.0 && $absSdi < 3.0) {
            $performance = 'QUESTIONABLE_WARNING';
            $isAcceptable = true;
            $actionRequired = false;
            $notes = 'PERINGATAN: Nilai SDI berada di rentang marginal (2.0 - 3.0 SD). Disarankan verifikasi kalibrasi instrumen.';
        } elseif ($absSdi >= 3.0) {
            $performance = 'UNSATISFACTORY';
            $isAcceptable = false;
            $actionRequired = true;
            $notes = 'TIDAK DAPAT DITERIMA: Nilai SDI >= 3.0 mengindikasikan bias analitik signifikan. Form Investigasi Galat PME & Tindakan Korektif (CAPA) wajib diterbitkan.';
        }

        return [
            'analyte' => strtoupper($analyteCode),
            'lab_result' => $labResult,
            'peer_group_mean' => $peerGroupMean,
            'peer_group_sd' => $peerGroupSd,
            'sdi' => $sdi,
            'percent_difference' => $percentDiff,
            'performance_category' => $performance,
            'is_acceptable' => $isAcceptable,
            'requires_corrective_action' => $actionRequired,
            'evaluation_notes' => $notes,
        ];
    }

    /**
     * Analisis diagram Youden 2-Sampel (Sample X vs Sample Y) untuk membedakan kesalahan acak vs sistematik.
     *
     * @param  string  $analyteCode  Kode analit
     * @param  float  $labResultSampleA  Hasil lab untuk Sampel A
     * @param  float  $meanSampleA  Rerata peer untuk Sampel A
     * @param  float  $sdSampleA  SD peer untuk Sampel A
     * @param  float  $labResultSampleB  Hasil lab untuk Sampel B
     * @param  float  $meanSampleB  Rerata peer untuk Sampel B
     * @param  float  $sdSampleB  SD peer untuk Sampel B
     * @return array{
     *     analyte: string,
     *     sdi_sample_a: float,
     *     sdi_sample_b: float,
     *     distance_from_origin: float,
     *     error_type: string,
     *     quadrant: int,
     *     interpretation: string,
     *     corrective_guidance: string
     * }
     */
    public function evaluateYoudenTwoSamplePlot(
        string $analyteCode,
        float $labResultSampleA,
        float $meanSampleA,
        float $sdSampleA,
        float $labResultSampleB,
        float $meanSampleB,
        float $sdSampleB
    ): array {
        $evalA = $this->evaluateSdi($analyteCode, $labResultSampleA, $meanSampleA, $sdSampleA);
        $evalB = $this->evaluateSdi($analyteCode, $labResultSampleB, $meanSampleB, $sdSampleB);

        $sdiA = $evalA['sdi'];
        $sdiB = $evalB['sdi'];

        // Jarak radial Euclidean dari titik asal (0, 0)
        $distance = round(sqrt(pow($sdiA, 2) + pow($sdiB, 2)), 2);

        // Menentukan Kuadran bidang Cartesius (A = X, B = Y)
        $quadrant = 1;
        if ($sdiA >= 0 && $sdiB >= 0) {
            $quadrant = 1;
        } elseif ($sdiA < 0 && $sdiB >= 0) {
            $quadrant = 2;
        } elseif ($sdiA < 0 && $sdiB < 0) {
            $quadrant = 3;
        } else {
            $quadrant = 4;
        }

        // Klasifikasi Galat:
        // Titik di Kuadran 1 (+/+) atau Kuadran 3 (-/-) di sepanjang garis 45 derajat = Galat Sistematik (Systematic Error/Bias)
        // Titik di Kuadran 2 (-/+) atau Kuadran 4 (+/-) tegak lurus garis 45 derajat = Galat Acak (Random Error/Imprecision)
        $isSystematic = ($quadrant === 1 || $quadrant === 3);
        $errorType = 'ACCEPTABLE_PRECISION';
        $interpretation = 'Hasil kedua sampel konsisten dalam batas presisi yang memuaskan.';
        $guidance = 'Pertahankan prosedur operasional baku (SOP) analitik rutin.';

        if ($distance > 2.5) {
            if ($isSystematic) {
                $direction = ($quadrant === 1) ? 'Positive Bias' : 'Negative Bias';
                $errorType = "SYSTEMATIC_ERROR_{$direction}";
                $interpretation = "Deviasi terjadi searah pada kedua sampel ({$direction}), menunjukkan galat sistematik konstan/proporsional.";
                $guidance = 'Periksa nilai kalibrasi instrumen, kadaluarsa reagen, drift optik fotometer, atau suhu inkubator.';
            } else {
                $errorType = 'RANDOM_ERROR';
                $interpretation = 'Deviasi berlawanan arah antara kedua sampel mengindikasikan galat acak (impresisi analitik).';
                $guidance = 'Periksa presisi pemipetan spesimen, adanya gelembung udara pada kuvet, atau kontaminasi reagen silang.';
            }
        }

        return [
            'analyte' => strtoupper($analyteCode),
            'sdi_sample_a' => $sdiA,
            'sdi_sample_b' => $sdiB,
            'distance_from_origin' => $distance,
            'error_type' => $errorType,
            'quadrant' => $quadrant,
            'interpretation' => $interpretation,
            'corrective_guidance' => $guidance,
        ];
    }
}
