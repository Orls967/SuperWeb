<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms;

use Carbon\CarbonInterface;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\BudgetVarianceCertificate;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\BudgetVarianceResultDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetGuardrailBreachException;

final class BudgetVarianceAnalyzer
{
    /**
     * Analisis varians anggaran rasional integer tanpa float.
     */
    public function analyze(
        BudgetVarianceInputDTO $input,
        ?CarbonInterface $timestamp = null,
        bool $throwOnBreach = false
    ): BudgetVarianceResultDTO {
        $allocated = $input->allocatedBudgetMinor;
        $totalCommitted = $input->actualSpentMinor + $input->encumberedMinor;
        $varianceAmount = $allocated - $totalCommitted;

        $denom = max(1, $allocated);
        $utilizationPermille = (int) intdiv($totalCommitted * 1000, $denom);

        if ($totalCommitted <= $allocated) {
            $classification = ($utilizationPermille >= 900) ? 'ON_TRACK' : 'FAVORABLE';
            $isBreached = false;
            $variancePermille = 1000 - $utilizationPermille; // Sisa pagu positif
        } else {
            $excessPermille = $utilizationPermille - 1000;
            $variancePermille = -$excessPermille; // Defisit negatif

            if ($excessPermille > $input->varianceBreachThresholdPermille) {
                $classification = 'CRITICAL_BREACH';
                $isBreached = true;
            } else {
                $classification = 'UNFAVORABLE_WARNING';
                $isBreached = false;
            }
        }

        if ($throwOnBreach && $isBreached) {
            throw new BudgetGuardrailBreachException(
                "Departemen [{$input->departmentCode}] mengalami CRITICAL_BREACH: Utilisasi ({$utilizationPermille} permille) melampaui toleransi ({$input->varianceBreachThresholdPermille} permille)."
            );
        }

        $ts = $timestamp ? $timestamp->toIso8601String() : '2026-11-11T07:00:00Z';
        $payload = [
            'department_code' => $input->departmentCode,
            'fiscal_year' => $input->fiscalYear,
            'allocated_minor' => $allocated,
            'committed_minor' => $totalCommitted,
            'variance_amount_minor' => $varianceAmount,
            'utilization_permille' => $utilizationPermille,
            'classification' => $classification,
            'is_breached' => $isBreached,
            'timestamp' => $ts,
        ];
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash('sha256', (string) $json);

        $certificate = new BudgetVarianceCertificate($payload, $signature);

        return new BudgetVarianceResultDTO(
            departmentCode: $input->departmentCode,
            fiscalYear: $input->fiscalYear,
            allocatedBudgetMinor: $allocated,
            totalCommittedMinor: $totalCommitted,
            varianceAmountMinor: $varianceAmount,
            utilizationPermille: $utilizationPermille,
            variancePermille: $variancePermille,
            classification: $classification,
            isBreached: $isBreached,
            certificate: $certificate
        );
    }
}
