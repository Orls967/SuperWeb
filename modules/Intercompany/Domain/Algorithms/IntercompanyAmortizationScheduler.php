<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\Certificates\IntercompanyAmortizationCertificate;
use Modules\Intercompany\Domain\DTOs\LoanAmortizationResultDTO;
use Modules\Intercompany\Domain\DTOs\LoanAmortizationScheduleItemDTO;

class IntercompanyAmortizationScheduler
{
    public function generateSchedule(
        int $principalMinor,
        int $baseRateBps,
        int $spreadBps,
        int $withholdingTaxBps,
        int $periods,
        string $startDate
    ): LoanAmortizationResultDTO {
        if ($principalMinor <= 0) {
            throw new InvalidArgumentException('Pokok pinjaman harus lebih besar dari nol.');
        }

        if ($periods <= 0) {
            throw new InvalidArgumentException('INVALID_PERIODS_COUNT: Jumlah periode pinjaman minimal 1.');
        }

        if ($baseRateBps < 0 || $spreadBps < 0 || $withholdingTaxBps < 0) {
            throw new InvalidArgumentException('Tingkat suku bunga dan pajak tidak boleh negatif.');
        }

        $totalRateBps = $baseRateBps + $spreadBps;
        $principalBase = (int) floor($principalMinor / $periods);
        $remainder = $principalMinor - ($principalBase * $periods);

        $schedules = [];
        $scheduleDetails = [];
        $currentBalance = $principalMinor;
        $totalInterestGross = 0;
        $totalTaxWithheld = 0;
        $totalInterestNet = 0;

        $dt = new DateTimeImmutable($startDate);

        for ($k = 1; $k <= $periods; $k++) {
            // Pada periode terakhir serap seluruh sisa remainder pokok ganjil
            $principalDue = ($k === $periods) ? ($principalBase + $remainder) : $principalBase;
            $dueDate = $dt->modify("+{$k} month")->format('Y-m-d');

            // Bunga kotor basis 30/360: (balance * rate_bps * 30) / (360 * 10000) = (balance * rate_bps) / 120000
            $interestGross = (int) floor(($currentBalance * $totalRateBps * 30) / 3600000);
            $taxWithheld = (int) floor(($interestGross * $withholdingTaxBps) / 10000);
            $interestNet = $interestGross - $taxWithheld;
            $cashDisbursed = $principalDue + $interestNet;
            $endingBalance = $currentBalance - $principalDue;

            $item = new LoanAmortizationScheduleItemDTO(
                periodNumber: $k,
                dueDate: $dueDate,
                beginningBalanceMinor: $currentBalance,
                principalDueMinor: $principalDue,
                interestGrossMinor: $interestGross,
                taxWithheldMinor: $taxWithheld,
                interestNetMinor: $interestNet,
                cashDisbursedMinor: $cashDisbursed,
                endingBalanceMinor: $endingBalance
            );

            $schedules[] = $item;
            $scheduleDetails[] = [
                'cash_disbursed' => $cashDisbursed,
                'due_date' => $dueDate,
                'ending_balance' => $endingBalance,
                'interest_gross' => $interestGross,
                'interest_net' => $interestNet,
                'period' => $k,
                'principal_due' => $principalDue,
                'tax_withheld' => $taxWithheld,
            ];

            $totalInterestGross += $interestGross;
            $totalTaxWithheld += $taxWithheld;
            $totalInterestNet += $interestNet;

            $currentBalance = $endingBalance;
        }

        $certificate = new IntercompanyAmortizationCertificate(
            totalPrincipalMinor: $principalMinor,
            baseRateBps: $baseRateBps,
            spreadBps: $spreadBps,
            withholdingTaxBps: $withholdingTaxBps,
            periods: $periods,
            totalInterestGrossMinor: $totalInterestGross,
            totalTaxWithheldMinor: $totalTaxWithheld,
            totalInterestNetMinor: $totalInterestNet,
            scheduleDetails: $scheduleDetails
        );

        return new LoanAmortizationResultDTO(
            totalPrincipalMinor: $principalMinor,
            baseRateBps: $baseRateBps,
            spreadBps: $spreadBps,
            withholdingTaxBps: $withholdingTaxBps,
            totalInterestGrossMinor: $totalInterestGross,
            totalTaxWithheldMinor: $totalTaxWithheld,
            totalInterestNetMinor: $totalInterestNet,
            schedules: $schedules,
            certificate: $certificate
        );
    }
}
