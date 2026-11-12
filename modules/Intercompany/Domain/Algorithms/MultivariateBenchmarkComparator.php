<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Intercompany\Domain\DTOs\MultivariateBenchmarkResultDTO;

/**
 * Algoritma Evaluasi Pembanding Multivariat Transfer Pricing (OECD & PMK 172/2023).
 *
 * Menganalisis rentang kewajaran harga transfer (arm's length range) menggunakan metode interkuartil (IQR),
 * penyesuaian modal kerja (working capital adjustment), penyesuaian risiko negara, eliminasi data pencilan (outliers),
 * dan kalkulasi nilai penyesuaian harga transfer akhir periode (transfer pricing true-up adjustment).
 * Seluruh kalkulasi menggunakan integer minor units (sen) dan basis points (bps) tanpa tipe float.
 */
class MultivariateBenchmarkComparator
{
    /**
     * Jalankan evaluasi komparatif multivariat komprehensif.
     *
     * @param array{
     *     rule_code: string,
     *     tested_entity_code: string,
     *     method: string,
     *     period: string,
     *     prime_rate_bps?: int,
     *     country_risk_premium_bps?: int
     * } $config
     * @param array{
     *     revenue_minor: int,
     *     cogs_minor: int,
     *     opex_minor: int,
     *     operating_profit_minor: int,
     *     accounts_receivable_minor: int,
     *     inventory_minor: int,
     *     accounts_payable_minor: int
     * } $testedPartyData
     * @param array<int, array{
     *     name: string,
     *     revenue_minor: int,
     *     cogs_minor: int,
     *     opex_minor: int,
     *     operating_profit_minor: int,
     *     accounts_receivable_minor: int,
     *     inventory_minor: int,
     *     accounts_payable_minor: int,
     *     country_risk_bps?: int
     * }> $comparatorsData
     */
    public function evaluate(
        array $config,
        array $testedPartyData,
        array $comparatorsData
    ): MultivariateBenchmarkResultDTO {
        $ruleCode = $config['rule_code'];
        $testedEntity = $config['tested_entity_code'];
        $method = strtoupper($config['method'] ?? 'TNMM');
        $period = $config['period'];
        $primeRateBps = $config['prime_rate_bps'] ?? 750; // Suku bunga acuan 7.50%

        if (empty($comparatorsData)) {
            throw new InvalidArgumentException('Daftar data pembanding independen (comparators) tidak boleh kosong.');
        }

        $testedRev = max(1, $testedPartyData['revenue_minor']);
        $testedOpProfit = $testedPartyData['operating_profit_minor'];

        // Hitung Margin Operasi Pihak yang Diuji (dalam basis points: 1% = 100 bps)
        $actualTestedMarginBps = intdiv($testedOpProfit * 10000, $testedRev);

        // Rasio Modal Kerja Pihak yang Diuji (dalam bps terhadap Revenue)
        $testedArRatioBps = intdiv($testedPartyData['accounts_receivable_minor'] * 10000, $testedRev);
        $testedInvRatioBps = intdiv($testedPartyData['inventory_minor'] * 10000, $testedRev);
        $testedApRatioBps = intdiv($testedPartyData['accounts_payable_minor'] * 10000, $testedRev);

        $processedComparators = [];
        $rawMargins = [];

        foreach ($comparatorsData as $comp) {
            $compRev = max(1, $comp['revenue_minor']);
            $compOpProfit = $comp['operating_profit_minor'];
            $compMarginBps = intdiv($compOpProfit * 10000, $compRev);

            // Penyesuaian Modal Kerja (Working Capital Adjustment / WCA)
            $compArRatioBps = intdiv($comp['accounts_receivable_minor'] * 10000, $compRev);
            $compInvRatioBps = intdiv($comp['inventory_minor'] * 10000, $compRev);
            $compApRatioBps = intdiv($comp['accounts_payable_minor'] * 10000, $compRev);

            // WCA = (Diff AR + Diff Inv - Diff AP) * prime_rate / 10000
            $netWcDiffBps = ($compArRatioBps - $testedArRatioBps)
                + ($compInvRatioBps - $testedInvRatioBps)
                - ($compApRatioBps - $testedApRatioBps);

            $wcAdjBps = intdiv($netWcDiffBps * $primeRateBps, 10000);

            // Penyesuaian Risiko Negara (Country Risk Premium)
            $countryRiskAdjBps = (int) ($comp['country_risk_bps'] ?? 0);

            $adjustedMarginBps = $compMarginBps + $wcAdjBps - $countryRiskAdjBps;

            $processedComparators[] = [
                'comparator_name' => $comp['name'],
                'operating_margin_bps' => $compMarginBps,
                'adjusted_margin_bps' => $adjustedMarginBps,
                'working_capital_adj_bps' => $wcAdjBps,
                'country_risk_adj_bps' => $countryRiskAdjBps,
                'is_outlier' => false,
            ];

            $rawMargins[] = $adjustedMarginBps;
        }

        // Urutkan nilai margin untuk kalkulasi statistik non-parametrik
        sort($rawMargins, SORT_NUMERIC);
        $count = count($rawMargins);

        // Deteksi Pencilan (Tukey's Fences)
        $prelimQ1 = $this->calculatePercentile($rawMargins, 25);
        $prelimQ3 = $this->calculatePercentile($rawMargins, 75);
        $prelimIqr = $prelimQ3 - $prelimQ1;
        $lowerFence = $prelimQ1 - intdiv($prelimIqr * 15, 10);
        $upperFence = $prelimQ3 + intdiv($prelimIqr * 15, 10);

        $filteredMargins = [];
        foreach ($processedComparators as $idx => $compInfo) {
            $val = $compInfo['adjusted_margin_bps'];
            if ($val < $lowerFence || $val > $upperFence) {
                $processedComparators[$idx]['is_outlier'] = true;
            } else {
                $filteredMargins[] = $val;
            }
        }

        if (empty($filteredMargins)) {
            $filteredMargins = $rawMargins;
        }

        sort($filteredMargins, SORT_NUMERIC);

        // Hitung Statistik Kuartil Definitif (PSAK / OECD Standard)
        $q1Bps = $this->calculatePercentile($filteredMargins, 25);
        $medianBps = $this->calculatePercentile($filteredMargins, 50);
        $q3Bps = $this->calculatePercentile($filteredMargins, 75);
        $iqrBps = $q3Bps - $q1Bps;
        $minBps = $filteredMargins[0];
        $maxBps = $filteredMargins[count($filteredMargins) - 1];

        $sum = 0;
        foreach ($filteredMargins as $mVal) {
            $sum += $mVal;
        }
        $meanBps = intdiv($sum, count($filteredMargins));

        $varianceSum = 0;
        foreach ($filteredMargins as $mVal) {
            $diff = $mVal - $meanBps;
            $varianceSum += ($diff * $diff);
        }
        $stdDevBps = (int) isqrt(intdiv($varianceSum, count($filteredMargins)));

        $interquartileRange = [
            'q1_bps' => $q1Bps,
            'median_bps' => $medianBps,
            'q3_bps' => $q3Bps,
            'iqr_bps' => $iqrBps,
            'mean_bps' => $meanBps,
            'std_dev_bps' => $stdDevBps,
            'min_bps' => $minBps,
            'max_bps' => $maxBps,
        ];

        // Evaluasi Kewajaran Marjin Pihak yang Diuji
        $isWithinRange = ($actualTestedMarginBps >= $q1Bps && $actualTestedMarginBps <= $q3Bps);
        $requiresTrueUp = ! $isWithinRange;

        $recommendedAdjustmentMinor = 0;
        $adjustmentDirection = 'NONE';
        $vouchers = [];

        if ($requiresTrueUp) {
            // Berdasarkan PMK 172/2023, penyesuaian dilakukan ke titik tengah (Median)
            $varianceBps = $medianBps - $actualTestedMarginBps;

            // Nominal Penyesuaian = Revenue * (Median Bps - Actual Bps) / 10000
            $recommendedAdjustmentMinor = intdiv($testedRev * abs($varianceBps), 10000);

            if ($actualTestedMarginBps < $q1Bps) {
                // Marjin terlalu rendah -> Tambah pendapatan/kredit margin
                $adjustmentDirection = 'UPWARD';
                $vouchers[] = [
                    'voucher_type' => 'TP_TRUE_UP_UPWARD',
                    'debit_account' => 'IC_AR_CLEARING',
                    'credit_account' => 'IC_REVENUE_ADJUSTMENT',
                    'amount_minor' => $recommendedAdjustmentMinor,
                    'memo' => "True-up penyesuaian kenaikan laba transfer pricing {$ruleCode} ke median {$medianBps} bps",
                ];
            } else {
                // Marjin terlalu tinggi -> Kurangi pendapatan/debit margin
                $adjustmentDirection = 'DOWNWARD';
                $vouchers[] = [
                    'voucher_type' => 'TP_TRUE_UP_DOWNWARD',
                    'debit_account' => 'IC_REVENUE_ADJUSTMENT',
                    'credit_account' => 'IC_AP_CLEARING',
                    'amount_minor' => $recommendedAdjustmentMinor,
                    'memo' => "True-up penyesuaian penurunan laba transfer pricing {$ruleCode} ke median {$medianBps} bps",
                ];
            }
        } else {
            $varianceBps = $actualTestedMarginBps - $medianBps;
        }

        $testedPartyEvaluation = [
            'actual_margin_bps' => $actualTestedMarginBps,
            'is_within_range' => $isWithinRange,
            'target_median_bps' => $medianBps,
            'variance_bps' => $varianceBps,
            'requires_true_up' => $requiresTrueUp,
            'recommended_adjustment_minor' => $recommendedAdjustmentMinor,
            'adjustment_direction' => $adjustmentDirection,
        ];

        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $hashSeed = sprintf(
            '%s|%s|%d|%d|%d|%d|%d',
            $ruleCode,
            $testedEntity,
            $actualTestedMarginBps,
            $q1Bps,
            $medianBps,
            $q3Bps,
            $recommendedAdjustmentMinor
        );
        $auditChecksum = hash('sha256', $hashSeed);

        return new MultivariateBenchmarkResultDTO(
            ruleCode: $ruleCode,
            testedEntityCode: $testedEntity,
            benchmarkMethod: $method,
            testedPeriod: $period,
            comparators: $processedComparators,
            interquartileRange: $interquartileRange,
            testedPartyEvaluation: $testedPartyEvaluation,
            trueUpJournalVouchers: $vouchers,
            evaluatedAt: $nowStr,
            auditChecksum: $auditChecksum
        );
    }

    /**
     * Hitung nilai persentil diskrit tanpa pembulatan float.
     *
     * @param  array<int, int>  $sortedValues
     */
    protected function calculatePercentile(array $sortedValues, int $percentile): int
    {
        $n = count($sortedValues);
        if ($n === 0) {
            return 0;
        }
        if ($n === 1) {
            return $sortedValues[0];
        }

        // Rumus posisi rank linier diskrit: rank = (percentile * (n - 1)) / 100
        $scaledRank = $percentile * ($n - 1);
        $index = intdiv($scaledRank, 100);
        $remainder = $scaledRank % 100;

        if ($remainder === 0 || $index >= $n - 1) {
            return $sortedValues[$index];
        }

        // Interpolasi terbobot integer
        $lower = $sortedValues[$index];
        $upper = $sortedValues[$index + 1];

        return $lower + intdiv(($upper - $lower) * $remainder, 100);
    }
}

/**
 * Helper akar kuadrat integer integer-only (Metode Babilonia / Newton-Raphson).
 */
function isqrt(int $num): int
{
    if ($num <= 0) {
        return 0;
    }
    $x0 = intdiv($num, 2);
    if ($x0 === 0) {
        return 1;
    }
    $x1 = intdiv($x0 + intdiv($num, $x0), 2);
    while ($x1 < $x0) {
        $x0 = $x1;
        $x1 = intdiv($x0 + intdiv($num, $x0), 2);
    }

    return $x0;
}
