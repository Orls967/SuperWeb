<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use InvalidArgumentException;

/**
 * Kalkulator standar System Usability Scale (SUS) John Brooke (1996) dan interpretasi Sauro-Lewis.
 */
class SusScoreCalculator
{
    /**
     * Pernyataan resmi 10 item kuesioner SUS standar.
     */
    public const SUS_QUESTIONS = [
        1 => 'Saya merasa ingin sering menggunakan sistem ini.',
        2 => 'Saya merasa sistem ini terlalu rumit tanpa alasan yang jelas.',
        3 => 'Saya merasa sistem ini mudah digunakan.',
        4 => 'Saya merasa memerlukan bantuan tenaga ahli untuk dapat menggunakan sistem ini.',
        5 => 'Saya menemukan berbagai fungsi dalam sistem ini terintegrasi dengan baik.',
        6 => 'Saya merasa ada terlalu banyak ketidakkonsistenan dalam sistem ini.',
        7 => 'Saya membayangkan sebagian besar orang akan mempelajari sistem ini dengan cepat.',
        8 => 'Saya merasa sistem ini sangat janggal atau rumit untuk digunakan.',
        9 => 'Saya merasa sangat percaya diri saat menggunakan sistem ini.',
        10 => 'Saya perlu mempelajari banyak hal baru sebelum saya dapat menggunakan sistem ini secara mandiri.',
    ];

    /**
     * Hitung skor SUS dari sebuah respons responden (array asosiatif 1..10 atau list 10 integer 1-5).
     *
     * @param  array<int, int>  $responses  Nilai 1 s/d 5 untuk masing-masing pertanyaan
     * @return array{score: float, contributions: array<int, int>, grade: string, adjective: string, acceptable: string, percentile: float, nps_category: string}
     */
    public function calculateSingleResponse(array $responses): array
    {
        if (count($responses) !== 10) {
            throw new InvalidArgumentException('Kuesioner SUS wajib memiliki tepat 10 jawaban.');
        }

        $contributions = [];
        $totalContributions = 0;

        for ($i = 1; $i <= 10; $i++) {
            $key = array_key_exists($i, $responses) ? $i : ($i - 1);
            if (! isset($responses[$key])) {
                throw new InvalidArgumentException("Jawaban untuk item ke-{$i} tidak ditemukan.");
            }

            $val = (int) $responses[$key];
            if ($val < 1 || $val > 5) {
                throw new InvalidArgumentException("Nilai skala item ke-{$i} harus berada di rentang 1 s/d 5 (diberikan: {$val}).");
            }

            // Ganjil: val - 1; Genap: 5 - val
            $contribution = ($i % 2 === 1) ? ($val - 1) : (5 - $val);
            $contributions[$i] = $contribution;
            $totalContributions += $contribution;
        }

        $rawScore = $totalContributions * 2.5;

        return [
            'score' => round($rawScore, 1),
            'contributions' => $contributions,
            'grade' => $this->resolveSauroLewisGrade($rawScore),
            'adjective' => $this->resolveBangorAdjective($rawScore),
            'acceptable' => $this->resolveAcceptability($rawScore),
            'percentile' => $this->resolvePercentile($rawScore),
            'nps_category' => $this->resolveNpsCategory($rawScore),
        ];
    }

    /**
     * Hitung agregat skor SUS dari sekumpulan responden riset UX.
     *
     * @param  array<int, array<int, int>>  $allResponses  Daftar respon responden
     * @return array{mean_score: float, median_score: float, std_dev: float, respondent_count: int, grade: string, adjective: string, acceptable: string, scores: array<int, float>}
     */
    public function calculateAggregate(array $allResponses): array
    {
        if (empty($allResponses)) {
            return [
                'mean_score' => 0.0,
                'median_score' => 0.0,
                'std_dev' => 0.0,
                'respondent_count' => 0,
                'grade' => 'N/A',
                'adjective' => 'Belum ada data',
                'acceptable' => 'Belum ada data',
                'scores' => [],
            ];
        }

        $scores = [];
        foreach ($allResponses as $resp) {
            $calc = $this->calculateSingleResponse($resp);
            $scores[] = $calc['score'];
        }

        $count = count($scores);
        $mean = array_sum($scores) / $count;

        // Hitung standar deviasi
        $variance = 0.0;
        foreach ($scores as $s) {
            $variance += pow($s - $mean, 2);
        }
        $stdDev = $count > 1 ? sqrt($variance / ($count - 1)) : 0.0;

        // Hitung median
        sort($scores);
        $mid = (int) floor($count / 2);
        $median = ($count % 2 === 1) ? $scores[$mid] : ($scores[$mid - 1] + $scores[$mid]) / 2.0;

        return [
            'mean_score' => round($mean, 1),
            'median_score' => round($median, 1),
            'std_dev' => round($stdDev, 2),
            'respondent_count' => $count,
            'grade' => $this->resolveSauroLewisGrade($mean),
            'adjective' => $this->resolveBangorAdjective($mean),
            'acceptable' => $this->resolveAcceptability($mean),
            'scores' => $scores,
        ];
    }

    /**
     * Skala peringkat huruf Sauro-Lewis (2016).
     */
    public function resolveSauroLewisGrade(float $score): string
    {
        if ($score >= 84.1) {
            return 'A+';
        }
        if ($score >= 80.8) {
            return 'A';
        }
        if ($score >= 78.9) {
            return 'A-';
        }
        if ($score >= 77.2) {
            return 'B+';
        }
        if ($score >= 74.1) {
            return 'B';
        }
        if ($score >= 72.6) {
            return 'B-';
        }
        if ($score >= 71.1) {
            return 'C+';
        }
        if ($score >= 65.0) {
            return 'C';
        }
        if ($score >= 62.7) {
            return 'C-';
        }
        if ($score >= 51.7) {
            return 'D';
        }

        return 'F';
    }

    /**
     * Peringkat Adjektiva Bangor dkk. (2009).
     */
    public function resolveBangorAdjective(float $score): string
    {
        if ($score >= 90.0) {
            return 'Best Imaginable';
        }
        if ($score >= 85.0) {
            return 'Excellent';
        }
        if ($score >= 71.4) {
            return 'Good';
        }
        if ($score >= 52.0) {
            return 'OK';
        }
        if ($score >= 39.0) {
            return 'Poor';
        }
        if ($score >= 25.0) {
            return 'Awful';
        }

        return 'Worst Imaginable';
    }

    /**
     * Rentang penerimaan (Acceptability Range).
     */
    public function resolveAcceptability(float $score): string
    {
        if ($score >= 70.0) {
            return 'Acceptable';
        }
        if ($score >= 50.0) {
            return 'Marginal';
        }

        return 'Not Acceptable';
    }

    /**
     * Estimasi persentil berdasarkan kurva distribusi Sauro (rata-rata industri = 68.0).
     */
    public function resolvePercentile(float $score): float
    {
        if ($score >= 84.0) {
            return 96.0;
        }
        if ($score >= 80.0) {
            return 90.0;
        }
        if ($score >= 74.0) {
            return 70.0;
        }
        if ($score >= 68.0) {
            return 50.0;
        }
        if ($score >= 60.0) {
            return 30.0;
        }
        if ($score >= 50.0) {
            return 15.0;
        }

        return 5.0;
    }

    /**
     * Estimasi korelasi Net Promoter Score (NPS).
     */
    public function resolveNpsCategory(float $score): string
    {
        if ($score >= 82.0) {
            return 'Promoter';
        }
        if ($score >= 68.0) {
            return 'Passive';
        }

        return 'Detractor';
    }
}
