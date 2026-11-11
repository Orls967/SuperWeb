<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms;

use Carbon\CarbonInterface;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\ConsolidatedTrialBalanceCertificate;
use Modules\EnterpriseFinance\Domain\DTOs\AggregatedAccountSummaryDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\ConsolidatedTrialBalanceResultDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\UnbalancedTrialBalanceException;

final class ConsolidatedTrialBalanceAggregator
{
    /**
     * Agregasi neraca saldo multi-entitas deterministik integer tanpa float.
     */
    public function aggregate(
        ConsolidatedTrialBalanceInputDTO $input,
        ?CarbonInterface $timestamp = null,
        bool $strict = false
    ): ConsolidatedTrialBalanceResultDTO {
        $totalDebit = 0;
        $totalCredit = 0;
        $accountMap = [];

        foreach ($input->entities as $entity) {
            foreach ($entity->accountBalances as $acc) {
                $code = $acc->canonicalAccountCode;
                $deb = $acc->debitMinor;
                $crd = $acc->creditMinor;

                $totalDebit += $deb;
                $totalCredit += $crd;

                if (! isset($accountMap[$code])) {
                    $accountMap[$code] = [
                        'code' => $code,
                        'name' => $acc->accountName,
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }

                $accountMap[$code]['debit'] += $deb;
                $accountMap[$code]['credit'] += $crd;
            }
        }

        $delta = $totalDebit - $totalCredit;
        $isZeroSum = ($delta === 0);

        if ($strict && ! $isZeroSum) {
            throw new UnbalancedTrialBalanceException($totalDebit, $totalCredit, $delta);
        }

        ksort($accountMap);
        $aggregatedAccounts = [];
        foreach ($accountMap as $item) {
            $aggregatedAccounts[] = new AggregatedAccountSummaryDTO(
                canonicalAccountCode: $item['code'],
                accountName: $item['name'],
                totalDebitMinor: $item['debit'],
                totalCreditMinor: $item['credit'],
                netBalanceMinor: $item['debit'] - $item['credit']
            );
        }

        $ts = $timestamp ? $timestamp->toIso8601String() : '2026-11-11T07:00:00Z';
        $payload = [
            'period' => $input->period,
            'reporting_currency' => $input->reportingCurrency,
            'total_entities_count' => count($input->entities),
            'total_debit_minor' => $totalDebit,
            'total_credit_minor' => $totalCredit,
            'delta_balance_minor' => $delta,
            'is_zero_sum_balanced' => $isZeroSum,
            'timestamp' => $ts,
        ];
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash('sha256', (string) $json);

        $certificate = new ConsolidatedTrialBalanceCertificate($payload, $signature);

        return new ConsolidatedTrialBalanceResultDTO(
            period: $input->period,
            reportingCurrency: $input->reportingCurrency,
            totalEntitiesCount: count($input->entities),
            totalDebitMinor: $totalDebit,
            totalCreditMinor: $totalCredit,
            deltaBalanceMinor: $delta,
            isZeroSumBalanced: $isZeroSum,
            aggregatedAccounts: $aggregatedAccounts,
            certificate: $certificate
        );
    }
}
