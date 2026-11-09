<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Layanan Pelacakan Jejak Kaset Blok Jaringan & Mutu Pra-Analitik Histopatologi (Fase 105).
 *
 * Mengelola rantai siklus hidup spesimen patologi anatomi (biopsi / reseksi jaringan):
 * 1. Cold Ischemia Time: Jeda waktu antara devaskularisasi/eksisi bedah hingga fiksasi formalin
 *    (Maksimal 60 menit sesuai standar ASCO/CAP untuk preservasi antigenik biomarker payudara ER/PR/HER2).
 * 2. Neutral Buffered Formalin (NBF 10%) Fixation Time:
 *    (Minimal 6 jam, optimal 24-48 jam, maksimal 72 jam untuk menghindari degradasi asam nukleat).
 * 3. Serialisasi Barcode Kaset Blok Parafin (Grossing & Cassette Embedding).
 * 4. Pemotongan Mikrotom & Serialisasi Kaca Preparat (Slide) H&E serta Imunohistokimia (IHC).
 * 5. Deteksi Deviasi Mutu & Kriteria Penolakan Jaringan.
 */
class HistopathologyTissueBlockTraceabilityService
{
    /**
     * Ambang batas durasi fiksasi formalin (jam) standar CAP / ISO 15189.
     */
    public const MIN_FIXATION_HOURS = 6.0;

    public const OPTIMAL_FIXATION_HOURS = 24.0;

    public const MAX_FIXATION_HOURS = 72.0;

    public const MAX_COLD_ISCHEMIA_MINUTES = 60.0;

    /**
     * Evaluasi parameter pra-analitik waktu iskemia dingin spesimen jaringan.
     *
     * @param  Carbon  $resectionTime  Waktu eksisi jaringan di kamar operasi
     * @param  Carbon  $fixationStartTime  Waktu jaringan dimasukkan ke cairan formalin 10% NBF
     * @param  string  $tissueType  Tipe jaringan (misal: 'BREAST_BIOPSY', 'COLON_RESECTION', 'LYMPH_NODE')
     * @return array{
     *     cold_ischemia_minutes: float,
     *     is_within_guidelines: bool,
     *     risk_level: string,
     *     biomarker_reliability: string,
     *     warning: string|null
     * }
     */
    public function evaluateColdIschemia(
        Carbon $resectionTime,
        Carbon $fixationStartTime,
        string $tissueType = 'GENERAL_TISSUE'
    ): array {
        if ($fixationStartTime->lessThan($resectionTime)) {
            throw new InvalidArgumentException('Waktu mulai fiksasi tidak boleh mendahului waktu eksisi bedah.');
        }

        $minutes = (float) $resectionTime->diffInRealMinutes($fixationStartTime);

        $isWithinGuidelines = $minutes <= self::MAX_COLD_ISCHEMIA_MINUTES;
        $riskLevel = 'LOW';
        $reliability = 'OPTIMAL_FOR_ALL_BIOMARKERS';
        $warning = null;

        if ($minutes > self::MAX_COLD_ISCHEMIA_MINUTES && $minutes <= 120.0) {
            $riskLevel = 'MODERATE';
            $reliability = 'SUBOPTIMAL_FOR_PHOSPHO_PROTEINS';
            $warning = "Cold ischemia time {$minutes} menit melampaui batas 60 menit ASCO/CAP. Risiko autolisis parsial.";
        } elseif ($minutes > 120.0) {
            $riskLevel = 'CRITICAL';
            $reliability = 'COMPROMISED_ANTIGENICITY';
            $warning = "Cold ischemia time kritis ({$minutes} menit). Risiko degradasi protein reseptor hormon (ER/PR/HER2) dan RNA tinggi.";
        }

        return [
            'cold_ischemia_minutes' => round($minutes, 1),
            'is_within_guidelines' => $isWithinGuidelines,
            'risk_level' => $riskLevel,
            'biomarker_reliability' => $reliability,
            'warning' => $warning,
        ];
    }

    /**
     * Evaluasi durasi fiksasi formalin netral 10% (NBF).
     *
     * @param  Carbon  $fixationStart  Waktu perendaman awal dalam formalin
     * @param  Carbon  $processingStart  Waktu pemrosesan jaringan di tissue processor
     * @return array{
     *     fixation_hours: float,
     *     status: string,
     *     is_acceptable: bool,
     *     advisory: string
     * }
     */
    public function evaluateFixationDuration(Carbon $fixationStart, Carbon $processingStart): array
    {
        if ($processingStart->lessThan($fixationStart)) {
            throw new InvalidArgumentException('Waktu mulai pemrosesan tidak boleh sebelum waktu fiksasi.');
        }

        $hours = round($fixationStart->diffInRealMinutes($processingStart) / 60.0, 2);

        if ($hours < self::MIN_FIXATION_HOURS) {
            return [
                'fixation_hours' => $hours,
                'status' => 'UNDER_FIXED',
                'is_acceptable' => false,
                'advisory' => "Jaringan under-fixed ({$hours} jam < 6 jam). Risiko morfologi inti sel mengkerut dan pewarnaan rapuh.",
            ];
        }

        if ($hours > self::MAX_FIXATION_HOURS) {
            return [
                'fixation_hours' => $hours,
                'status' => 'OVER_FIXED',
                'is_acceptable' => false,
                'advisory' => "Jaringan over-fixed ({$hours} jam > 72 jam). Cross-linking formalin berlebih dapat menghambat antigen retrieval IHC.",
            ];
        }

        return [
            'fixation_hours' => $hours,
            'status' => 'ADEQUATE',
            'is_acceptable' => true,
            'advisory' => "Durasi fiksasi optimal ({$hours} jam). Sesuai dengan standar histoteknologi diagnostik.",
        ];
    }

    /**
     * Buat manifest kaset blok parafin dan barcode kaca preparat serial.
     *
     * @param  string  $caseNumber  Nomor kasus patologi (misal: 'PA-2026-00421')
     * @param  string  $specimenPart  Bagian spesimen (misal: 'A', 'B', 'C')
     * @param  int  $numberOfBlocks  Jumlah blok parafin yang dipotong (grossing)
     * @param  list<string>  $stainingProtocols  Daftar protokol pewarnaan per blok (misal: ['HE', 'PAS', 'IHC_HER2'])
     * @return array{
     *     case_number: string,
     *     specimen_part: string,
     *     total_blocks: int,
     *     total_slides: int,
     *     blocks: list<array{
     *         block_id: string,
     *         block_label: string,
     *         barcode: string,
     *         slides: list<array{slide_id: string, slide_label: string, stain: string, barcode: string}>
     *     }>
     * }
     */
    public function generateBlockAndSlideManifest(
        string $caseNumber,
        string $specimenPart,
        int $numberOfBlocks,
        array $stainingProtocols = ['HE']
    ): array {
        if ($numberOfBlocks <= 0) {
            throw new InvalidArgumentException('Jumlah blok kaset minimal 1.');
        }

        $blocks = [];
        $totalSlides = 0;

        for ($b = 1; $b <= $numberOfBlocks; $b++) {
            $blockSuffix = sprintf('%s%d', strtoupper($specimenPart), $b);
            $blockId = "{$caseNumber}-{$blockSuffix}";
            $blockBarcode = 'BLK*'.str_replace('-', '', $caseNumber)."*{$blockSuffix}";

            $slides = [];
            $slideIndex = 1;

            foreach ($stainingProtocols as $stain) {
                $slideLabel = "{$blockSuffix}-S{$slideIndex}";
                $slideId = "{$caseNumber}-{$slideLabel}";
                $slideBarcode = 'SLD*'.str_replace('-', '', $caseNumber)."*{$slideLabel}*{$stain}";

                $slides[] = [
                    'slide_id' => $slideId,
                    'slide_label' => $slideLabel,
                    'stain' => strtoupper($stain),
                    'barcode' => $slideBarcode,
                ];

                $slideIndex++;
                $totalSlides++;
            }

            $blocks[] = [
                'block_id' => $blockId,
                'block_label' => "Block {$blockSuffix}",
                'barcode' => $blockBarcode,
                'slides' => $slides,
            ];
        }

        return [
            'case_number' => $caseNumber,
            'specimen_part' => strtoupper($specimenPart),
            'total_blocks' => $numberOfBlocks,
            'total_slides' => $totalSlides,
            'blocks' => $blocks,
        ];
    }
}
