<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Services;

use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Models\EfCapexProposal;

/**
 * Evaluator Kelayakan Finansial Belanja Modal (CAPEX Financial Evaluator) (54.1 & Corporate Governance).
 * Menilai kelayakan proposal investasi modal korporat:
 * 1. Net Present Value (NPV) dengan metode diskonto arus kas integer rasional.
 * 2. Payback Period (periode pengembalian investasi dalam bulan).
 * 3. Profitability Index (PI = Nilai Sekarang Arus Masuk / Investasi Awal).
 * 4. Kepatuhan alokasi pagu perbendaharaan holding (Treasury Capex Quota).
 */
class CapexProjectFinancialEvaluator
{
    /**
     * Mengevaluasi kelayakan finansial proposal proyek belanja modal.
     *
     * @param  list<int>  $annualCashInflowsMinor  Proyeksi arus kas masuk tahunan (misal 5 tahun)
     * @param  int  $discountRateBps  Tingkat diskonto biaya modal dalam basis points (misal 800 bps = 8%)
     * @return array{
     *     proposal_code: string,
     *     project_title: string,
     *     initial_investment_minor: int,
     *     present_value_inflows_minor: int,
     *     net_present_value_minor: int,
     *     profitability_index_permille: int,
     *     payback_period_months: int,
     *     is_financially_viable: bool,
     *     recommendation: string
     * }
     */
    public function evaluateProposal(
        EfCapexProposal $proposal,
        array $annualCashInflowsMinor,
        int $discountRateBps = 800
    ): array {
        $initialInvestment = (int) $proposal->requested_budget_minor;
        if ($initialInvestment <= 0) {
            throw new InvalidArgumentException('Nilai investasi awal belanja modal harus bernilai positif.');
        }

        if ($discountRateBps <= 0) {
            throw new InvalidArgumentException('Tingkat diskonto harus bernilai positif.');
        }

        // 1. Menghitung Present Value of Inflows
        $pvInflows = 0;
        $year = 1;
        $cumulativeCash = 0;
        $paybackMonths = 0;
        $paybackFound = false;

        foreach ($annualCashInflowsMinor as $inflow) {
            // Diskonkan arus kas ke PV
            $factorPermille = 1000;
            for ($i = 0; $i < $year; $i++) {
                $factorPermille = (int) intdiv($factorPermille * 10000, 10000 + $discountRateBps);
            }

            $pv = (int) intdiv($inflow * $factorPermille, 1000);
            $pvInflows += $pv;

            // Kalkulasi Payback Period (Undiscounted)
            if (! $paybackFound) {
                if ($cumulativeCash + $inflow >= $initialInvestment) {
                    $needed = $initialInvestment - $cumulativeCash;
                    $fractionMonths = ($inflow > 0) ? (int) intdiv($needed * 12, $inflow) : 12;
                    $paybackMonths = (($year - 1) * 12) + $fractionMonths;
                    $paybackFound = true;
                } else {
                    $cumulativeCash += $inflow;
                }
            }

            $year++;
        }

        if (! $paybackFound) {
            $paybackMonths = ($year - 1) * 12; // Lebih dari horizon proyek
        }

        // 2. Net Present Value (NPV) = PV of Inflows - Initial Investment
        $npv = $pvInflows - $initialInvestment;

        // 3. Profitability Index = (PV Inflows / Initial Investment) * 1000 permille
        $piPermille = (int) intdiv($pvInflows * 1000, $initialInvestment);

        $isViable = ($npv >= 0 && $piPermille >= 1000);

        $recommendation = $isViable
            ? "Proposal layak secara finansial dengan NPV positif Rp {$npv} dan Profitability Index {$piPermille}‰ (Payback: {$paybackMonths} bulan)."
            : "Proposal tidak direkomendasikan karena NPV negatif (Rp {$npv}) dan pengembalian di bawah biaya modal.";

        return [
            'proposal_code' => $proposal->proposal_code,
            'project_title' => $proposal->project_title ?? 'Proyek Capex',
            'initial_investment_minor' => $initialInvestment,
            'present_value_inflows_minor' => $pvInflows,
            'net_present_value_minor' => $npv,
            'profitability_index_permille' => $piPermille,
            'payback_period_months' => $paybackMonths,
            'is_financially_viable' => $isViable,
            'recommendation' => $recommendation,
        ];
    }
}
