<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Islamic;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Algoritma B6: GigCreditLimit
 *
 * Penentuan plafon kredit mikro syariah gig worker berbasis riwayat pendapatan,
 * koefisien variasi (volatilitas pendapatan), dan skor amanah.
 */
final class GigCreditLimit
{
    /**
     * @param  string  $workerPartyId  Identitas gig worker
     * @param  array<int, int>  $monthlyIncomes  Histori pendapatan bulanan (dalam sen)
     * @param  int  $amanahScore  Skor amanah / reputasi kerja (0 - 100)
     * @param  int  $maxDsrBps  Batas Debt Service Ratio maksimal (misal 3000 bps = 30%)
     * @param  int  $tenorMonths  Jangka waktu tenor pembiayaan mikro (bulan)
     */
    public static function calculate(
        string $workerPartyId,
        array $monthlyIncomes,
        int $amanahScore,
        int $maxDsrBps = 3000,
        int $tenorMonths = 12
    ): GigCreditLimitResult {
        if ($workerPartyId === '') {
            throw new InvalidArgumentException('workerPartyId tidak boleh kosong.');
        }

        if (count($monthlyIncomes) < 3) {
            throw new InvalidArgumentException('Riwayat pendapatan bulanan minimal 3 bulan.');
        }

        if ($amanahScore < 0 || $amanahScore > 100) {
            throw new InvalidArgumentException('Amanah score harus berada dalam rentang 0 - 100.');
        }

        if ($maxDsrBps <= 0 || $tenorMonths <= 0) {
            throw new InvalidArgumentException('DSR dan tenor harus positif.');
        }

        $n = count($monthlyIncomes);
        $sum = array_sum($monthlyIncomes);
        $mean = (int) intdiv($sum, $n);

        // Jika pendapatan rata-rata <= 0 maka tidak eligible
        if ($mean <= 0) {
            return self::buildResult(
                workerPartyId: $workerPartyId,
                monthlyIncomes: $monthlyIncomes,
                amanahScore: $amanahScore,
                maxDsrBps: $maxDsrBps,
                tenorMonths: $tenorMonths,
                mean: 0,
                volatilityBps: 10000,
                adjustedDsrBps: 0,
                maxMonthlyInstallment: 0,
                maxCreditLimit: 0,
                riskGrade: 'UNACCEPTABLE',
                isEligible: false
            );
        }

        // Variansi dan Standar Deviasi
        $varianceSum = 0.0;
        foreach ($monthlyIncomes as $inc) {
            $diff = (float) ($inc - $mean);
            $varianceSum += ($diff * $diff);
        }
        $stdDev = sqrt($varianceSum / $n);
        $volatilityRatio = $stdDev / (float) $mean;
        $volatilityBps = (int) round($volatilityRatio * 10000);

        // Penyesuaian DSR berdasarkan volatilitas pendapatan
        $adjustedDsrBps = $maxDsrBps;
        if ($volatilityBps > 4000) {
            // Volatilitas > 40%: diskon DSR 30%
            $adjustedDsrBps = (int) intdiv($maxDsrBps * 70, 100);
        } elseif ($volatilityBps > 2000) {
            // Volatilitas > 20%: diskon DSR 15%
            $adjustedDsrBps = (int) intdiv($maxDsrBps * 85, 100);
        }

        // Kapasitas cicilan bulanan kotor
        $rawMonthlyCapacity = (int) intdiv($mean * $adjustedDsrBps, 10000);

        // Penyesuaian Skor Amanah (bobot 50% s.d. 100%)
        $amanahFactor = max(50, min(100, $amanahScore)) / 100.0;
        $maxMonthlyInstallment = (int) round($rawMonthlyCapacity * $amanahFactor);

        // Plafon total pembiayaan
        $maxCreditLimit = $maxMonthlyInstallment * $tenorMonths;

        // Klasifikasi Grade Risiko
        $riskGrade = 'LOW_RISK';
        if ($amanahScore < 60 || $volatilityBps > 4500) {
            $riskGrade = 'HIGH_RISK';
        } elseif ($amanahScore < 80 || $volatilityBps > 2500) {
            $riskGrade = 'MEDIUM_RISK';
        }

        $isEligible = ($amanahScore >= 50 && $maxCreditLimit >= 50000000); // minimal plafon Rp 500.000

        return self::buildResult(
            workerPartyId: $workerPartyId,
            monthlyIncomes: $monthlyIncomes,
            amanahScore: $amanahScore,
            maxDsrBps: $maxDsrBps,
            tenorMonths: $tenorMonths,
            mean: $mean,
            volatilityBps: $volatilityBps,
            adjustedDsrBps: $adjustedDsrBps,
            maxMonthlyInstallment: $maxMonthlyInstallment,
            maxCreditLimit: $maxCreditLimit,
            riskGrade: $riskGrade,
            isEligible: $isEligible
        );
    }

    /**
     * @param  array<int, int>  $monthlyIncomes
     */
    private static function buildResult(
        string $workerPartyId,
        array $monthlyIncomes,
        int $amanahScore,
        int $maxDsrBps,
        int $tenorMonths,
        int $mean,
        int $volatilityBps,
        int $adjustedDsrBps,
        int $maxMonthlyInstallment,
        int $maxCreditLimit,
        string $riskGrade,
        bool $isEligible
    ): GigCreditLimitResult {
        $payload = [
            'algorithm' => 'B6-GigCreditLimit',
            'version' => '1.0',
            'inputs' => [
                'worker_party_id' => $workerPartyId,
                'monthly_incomes' => $monthlyIncomes,
                'amanah_score' => $amanahScore,
                'max_dsr_bps' => $maxDsrBps,
                'tenor_months' => $tenorMonths,
            ],
            'outputs' => [
                'average_monthly_income' => $mean,
                'income_volatility_bps' => $volatilityBps,
                'adjusted_dsr_bps' => $adjustedDsrBps,
                'max_monthly_installment' => $maxMonthlyInstallment,
                'max_credit_limit' => $maxCreditLimit,
                'risk_grade' => $riskGrade,
                'is_eligible' => $isEligible,
            ],
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $certificate = [
            'payload' => $payload,
            'hash' => $hash,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        return new GigCreditLimitResult(
            averageMonthlyIncome: $mean,
            incomeVolatilityBps: $volatilityBps,
            adjustedDsrBps: $adjustedDsrBps,
            maxMonthlyInstallment: $maxMonthlyInstallment,
            maxCreditLimit: $maxCreditLimit,
            riskGrade: $riskGrade,
            isEligible: $isEligible,
            certificate: $certificate
        );
    }
}
