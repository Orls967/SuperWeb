<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms;

use Carbon\CarbonInterface;
use Modules\EnterpriseFinance\Domain\Algorithms\Certificates\SyndicatedLoanCovenantCertificate;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantInputDTO;
use Modules\EnterpriseFinance\Domain\DTOs\SyndicatedLoanCovenantResultDTO;
use Modules\EnterpriseFinance\Domain\Exceptions\CovenantBreachException;

final class SyndicatedLoanCovenantCalculator
{
    /**
     * Hitung rasio covenant utang sindikasi deterministik integer permille.
     *
     * @param  bool  $throwOnBreach  Bila true, melempar exception saat terjadi breach
     */
    public function calculate(
        SyndicatedLoanCovenantInputDTO $input,
        ?CarbonInterface $timestamp = null,
        bool $throwOnBreach = false
    ): SyndicatedLoanCovenantResultDTO {
        $minDscr = $input->thresholds['min_dscr_permille'] ?? 1250;
        $maxDer = $input->thresholds['max_der_permille'] ?? 2500;
        $minIscr = $input->thresholds['min_iscr_permille'] ?? 2000;
        $minCr = $input->thresholds['min_current_ratio_permille'] ?? 1100;

        // 1. DSCR = intdiv((EBITDA - Tax) * 1000, DebtService)
        $netCashFlow = $input->ebitdaMinor - $input->taxPaidMinor;
        $dscrDebtService = max(1, $input->debtServiceMinor);
        $dscrPermille = (int) intdiv($netCashFlow * 1000, $dscrDebtService);

        // 2. DER = intdiv(TotalDebt * 1000, TotalEquity)
        $derEquity = max(1, $input->totalEquityMinor);
        $derPermille = (int) intdiv($input->totalInterestBearingDebtMinor * 1000, $derEquity);

        // 3. ISCR = intdiv(EBIT * 1000, InterestExpense)
        $iscrInterest = max(1, $input->interestExpenseMinor);
        $iscrPermille = (int) intdiv($input->ebitMinor * 1000, $iscrInterest);

        // 4. Current Ratio = intdiv(CurrentAssets * 1000, CurrentLiabilities)
        $crLiabilities = max(1, $input->currentLiabilitiesMinor);
        $currentRatioPermille = (int) intdiv($input->currentAssetsMinor * 1000, $crLiabilities);

        // Kepatuhan
        $isDscrCompliant = ($dscrPermille >= $minDscr);
        $isDerCompliant = ($derPermille <= $maxDer);
        $isIscrCompliant = ($iscrPermille >= $minIscr);
        $isCrCompliant = ($currentRatioPermille >= $minCr);

        $isFullyCompliant = ($isDscrCompliant && $isDerCompliant && $isIscrCompliant && $isCrCompliant);

        if ($throwOnBreach) {
            if (! $isDscrCompliant) {
                throw new CovenantBreachException($input->facilityCode, 'DSCR', $dscrPermille, $minDscr);
            }
            if (! $isDerCompliant) {
                throw new CovenantBreachException($input->facilityCode, 'DER', $derPermille, $maxDer);
            }
            if (! $isIscrCompliant) {
                throw new CovenantBreachException($input->facilityCode, 'ISCR', $iscrPermille, $minIscr);
            }
            if (! $isCrCompliant) {
                throw new CovenantBreachException($input->facilityCode, 'Current Ratio', $currentRatioPermille, $minCr);
            }
        }

        // Susun payload sertifikat
        $ts = $timestamp ? $timestamp->toIso8601String() : '2026-11-11T07:00:00Z';
        $payload = [
            'facility_code' => $input->facilityCode,
            'evaluation_period' => $input->evaluationPeriod,
            'dscr_permille' => $dscrPermille,
            'der_permille' => $derPermille,
            'iscr_permille' => $iscrPermille,
            'current_ratio_permille' => $currentRatioPermille,
            'is_fully_compliant' => $isFullyCompliant,
            'timestamp' => $ts,
        ];
        ksort($payload);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash('sha256', (string) $json);

        $certificate = new SyndicatedLoanCovenantCertificate($payload, $signature);

        return new SyndicatedLoanCovenantResultDTO(
            facilityCode: $input->facilityCode,
            evaluationPeriod: $input->evaluationPeriod,
            dscrPermille: $dscrPermille,
            derPermille: $derPermille,
            iscrPermille: $iscrPermille,
            currentRatioPermille: $currentRatioPermille,
            isDscrCompliant: $isDscrCompliant,
            isDerCompliant: $isDerCompliant,
            isIscrCompliant: $isIscrCompliant,
            isCurrentRatioCompliant: $isCrCompliant,
            isFullyCompliant: $isFullyCompliant,
            certificate: $certificate
        );
    }
}
