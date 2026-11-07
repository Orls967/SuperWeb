<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

/**
 * Algorithm M11: Commercial Metals Assay Valuation & Umpire Settlement Calculator.
 *
 * Evaluates commercial payment differences between provisional assay invoicing
 * and final umpire-settled assay results, applying standard commercial splitting limit tolerances
 * and allocating independent referee inspection fees to the outlier party.
 */
final class MetalsAssayInvoiceSettlementCalculator
{
    public function calculate(AssaySplitComparison $input): MetalsAssayInvoiceSettlementResult
    {
        $gradeDiff = abs($input->sellerNiGradePct - $input->buyerNiGradePct);
        $isDispute = $gradeDiff > $input->splittingLimitPct;

        $agreedGrade = 0.0;
        $isUmpire = false;
        $feeBearingParty = 'NONE';
        $sellerUmpireCost = 0;
        $buyerUmpireCost = 0;

        if (! $isDispute) {
            // Within splitting limit tolerance: settle at arithmetic mean of seller and buyer
            $agreedGrade = round(($input->sellerNiGradePct + $input->buyerNiGradePct) / 2.0, 3);
        } else {
            // Exceeds splitting limit: umpire required
            $isUmpire = true;
            $umpireGrade = $input->umpireNiGradePct ?? (($input->sellerNiGradePct + $input->buyerNiGradePct) / 2.0);
            $agreedGrade = round($umpireGrade, 3);

            // Determine fee bearing party: outlier pays
            $sellerDist = abs($input->sellerNiGradePct - $umpireGrade);
            $buyerDist = abs($input->buyerNiGradePct - $umpireGrade);

            if (abs($sellerDist - $buyerDist) < 0.001) {
                // Exact split
                $feeBearingParty = 'SPLIT_EQUAL';
                $sellerUmpireCost = (int) round($input->umpireServiceFeeIdr / 2);
                $buyerUmpireCost = $input->umpireServiceFeeIdr - $sellerUmpireCost;
            } elseif ($sellerDist > $buyerDist) {
                $feeBearingParty = 'SELLER';
                $sellerUmpireCost = $input->umpireServiceFeeIdr;
            } else {
                $feeBearingParty = 'BUYER';
                $buyerUmpireCost = $input->umpireServiceFeeIdr;
            }
        }

        // 1. Provisional invoice based on seller assay and provisional payment drawing rate (e.g. 85%)
        $fullProvisionalValUsd = $input->lotNetWeightTons * ($input->sellerNiGradePct / 100.0) * $input->lmeOfficialPriceUsdPerTon;
        $provisionalRate = $input->provisionalPaymentRateBps / 10000.0;
        $provisionalInvoiceIdr = (int) round($fullProvisionalValUsd * $provisionalRate * $input->exchangeRateIdrPerUsd);

        // 2. Final settlement based on agreed final grade and full 100% entitlement
        $finalValUsd = $input->lotNetWeightTons * ($agreedGrade / 100.0) * $input->lmeOfficialPriceUsdPerTon;
        $finalPayableValueIdr = (int) round($finalValUsd * $input->exchangeRateIdrPerUsd);

        // 3. Differential payable (positive = buyer pays remainder to seller; negative = seller refunds excess)
        $settlementDifferentialIdr = $finalPayableValueIdr - $provisionalInvoiceIdr;

        $verificationHash = hash('sha256', sprintf(
            '%.3f:%d:%d:%d:%d:%s',
            $agreedGrade,
            $provisionalInvoiceIdr,
            $finalPayableValueIdr,
            $settlementDifferentialIdr,
            $sellerUmpireCost + $buyerUmpireCost,
            $feeBearingParty
        ));

        return new MetalsAssayInvoiceSettlementResult(
            agreedFinalNiGradePct: $agreedGrade,
            isDisputeTriggered: $isDispute,
            isUmpireResolved: $isUmpire,
            umpireFeeBearingParty: $feeBearingParty,
            provisionalInvoiceIdr: $provisionalInvoiceIdr,
            finalPayableValueIdr: $finalPayableValueIdr,
            settlementDifferentialIdr: $settlementDifferentialIdr,
            sellerUmpireCostAllocationIdr: $sellerUmpireCost,
            buyerUmpireCostAllocationIdr: $buyerUmpireCost,
            verificationHash: $verificationHash
        );
    }
}
