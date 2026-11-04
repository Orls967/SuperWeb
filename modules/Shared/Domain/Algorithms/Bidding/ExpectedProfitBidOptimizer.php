<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Bidding;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Optimasi penetapan harga penawaran berbasis nilai harapan laba E = (p - c) * P (D84-2).
 * P(menang | p) = proporsi riwayat h dengan h > p (seri = kalah).
 * Kandidat p = h - langkah untuk setiap h unik.
 * Syarat: P >= min_win_bps / 10000 dan p >= c * (10000 + min_margin_bps) / 10000.
 * Tie-break: p lebih kecil.
 */
final class ExpectedProfitBidOptimizer
{
    private readonly BidOptimizationCertificateVerifier $verifier;

    public function __construct()
    {
        $this->verifier = new BidOptimizationCertificateVerifier;
    }

    /**
     * @param  list<int>  $history  Riwayat penawaran terendah pesaing
     * @param  int  $c  Estimasi biaya dasar integer
     * @param  int  $step  Langkah penurunan harga (default 5)
     * @param  int  $minWinBps  Ambang batas probabilitas menang minimum (default 3.000 bps = 30%)
     * @param  int  $minMarginBps  Ambang batas margin keuntungan minimum (default 0 bps)
     */
    public function optimize(
        int $c,
        array $history,
        int $step = 5,
        int $minWinBps = 3000,
        int $minMarginBps = 0
    ): BidOptimizationResult {
        if ($history === []) {
            throw new InvalidArgumentException('Riwayat penawaran tidak boleh kosong');
        }

        if ($step <= 0) {
            throw new InvalidArgumentException("Langkah harga harus positif: {$step}");
        }

        $n = count($history);
        $uniqueH = array_values(array_unique($history));
        sort($uniqueH, SORT_NUMERIC);

        $minWinRatio = BigRational::ofFraction($minWinBps, 10000);
        $minPriceThreshold = BigRational::of($c)
            ->multipliedBy(BigRational::ofFraction(10000 + $minMarginBps, 10000));

        $candidateEvals = [];
        $validCandidates = [];

        foreach ($uniqueH as $h) {
            $p = $h - $step;

            // P(menang | p) = proporsi riwayat h dengan h > p
            $winCount = 0;
            foreach ($history as $hVal) {
                if ($hVal > $p) {
                    $winCount++;
                }
            }

            $pWin = BigRational::ofFraction($winCount, $n);
            $margin = BigRational::of($p - $c);
            $expectedProfit = $margin->multipliedBy($pWin);

            $pWinDec = BigDecimal::of($pWin->getNumerator())->dividedBy(
                BigDecimal::of($pWin->getDenominator()),
                4,
                RoundingMode::HalfUp
            );

            $expDec = BigDecimal::of($expectedProfit->getNumerator())->dividedBy(
                BigDecimal::of($expectedProfit->getDenominator()),
                3,
                RoundingMode::HalfUp
            );

            $meetsWin = $pWin->isGreaterThanOrEqualTo($minWinRatio);
            $meetsMargin = BigRational::of($p)->isGreaterThanOrEqualTo($minPriceThreshold);

            $candidateEvals[] = [
                'h' => $h,
                'p' => $p,
                'p_win' => (string) $pWinDec,
                'expected_profit' => (string) $expDec,
                'meets_constraints' => $meetsWin && $meetsMargin,
            ];

            if ($meetsWin && $meetsMargin) {
                $validCandidates[] = [
                    'p' => $p,
                    'pWin' => $pWin,
                    'expectedProfit' => $expectedProfit,
                ];
            }
        }

        if ($validCandidates === []) {
            // Jika tidak ada yang memenuhi syarat, ambil kandidat dengan p >= minPriceThreshold atau terbaik
            throw new InvalidArgumentException('Tidak ada kandidat harga yang memenuhi syarat probabilitas menang dan margin');
        }

        // Cari E maksimum, tie-break p lebih kecil
        $best = $validCandidates[0];
        for ($i = 1; $i < count($validCandidates); $i++) {
            $curr = $validCandidates[$i];
            if ($curr['expectedProfit']->isGreaterThan($best['expectedProfit'])) {
                $best = $curr;
            } elseif ($curr['expectedProfit']->isEqualTo($best['expectedProfit'])) {
                if ($curr['p'] < $best['p']) {
                    $best = $curr;
                }
            }
        }

        $pWinFormatted = (string) BigDecimal::of($best['pWin']->getNumerator())->dividedBy(
            BigDecimal::of($best['pWin']->getDenominator()),
            4,
            RoundingMode::HalfUp
        );

        $expectedProfitFormatted = (string) BigDecimal::of($best['expectedProfit']->getNumerator())->dividedBy(
            BigDecimal::of($best['expectedProfit']->getDenominator()),
            3,
            RoundingMode::HalfUp
        );

        $payload = sprintf(
            '%d|%d|%d|%d|%s|%s',
            $c,
            $step,
            $minWinBps,
            $best['p'],
            $expectedProfitFormatted,
            $pWinFormatted
        );

        $certificate = [
            'is_valid' => true,
            'optimal_price' => $best['p'],
            'expected_profit' => $expectedProfitFormatted,
            'p_win' => $pWinFormatted,
            'hash' => hash('sha256', $payload),
        ];

        $result = new BidOptimizationResult(
            c: $c,
            step: $step,
            history: $history,
            minWinBps: $minWinBps,
            minMarginBps: $minMarginBps,
            optimalPrice: $best['p'],
            pWin: $best['pWin'],
            pWinFormatted: $pWinFormatted,
            expectedProfit: $best['expectedProfit'],
            expectedProfitFormatted: $expectedProfitFormatted,
            candidateEvaluations: $candidateEvals,
            certificate: $certificate
        );

        $this->verifier->verify($result);

        return $result;
    }
}
