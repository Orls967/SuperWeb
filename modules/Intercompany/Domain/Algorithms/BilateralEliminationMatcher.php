<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use Modules\Intercompany\Domain\Algorithms\Certificates\BilateralEliminationCertificate;
use Modules\Intercompany\Domain\DTOs\BilateralBalanceDTO;
use Modules\Intercompany\Domain\DTOs\BilateralEliminationResultDTO;
use Modules\Intercompany\Domain\DTOs\EliminationMatchItemDTO;

class BilateralEliminationMatcher
{
    /**
     * @param  array<int, string>  $periods
     * @param  array<int, BilateralBalanceDTO>  $pairs
     */
    public function matchPairs(array $periods, array $pairs): BilateralEliminationResultDTO
    {
        $matchItems = [];
        $totalEliminatedBalance = 0;
        $totalEliminatedPnl = 0;
        $totalInTransit = 0;
        $totalDiscrepancy = 0;
        $matrixDetails = [];

        foreach ($pairs as $pair) {
            $ar = $pair->receivableAmountIdr;
            $ap = $pair->payableAmountIdr;
            $discrepancy = $pair->discrepancyIdr();
            $inTransit = $pair->inTransitAmountIdr;

            $status = 'MATCHED_EXACT';
            $eliminatedBalance = min($ar, $ap);
            $eliminatedPnl = min($pair->salesRevenueIdr, $pair->cogsExpenseIdr);

            if ($discrepancy > 0) {
                if ($inTransit > 0 && abs($discrepancy - $inTransit) === 0) {
                    $status = 'IN_TRANSIT_DETECTED';
                } else {
                    $status = 'DISCREPANCY_DETECTED';
                }
            }

            $item = new EliminationMatchItemDTO(
                sellerEntity: $pair->sellerEntity,
                buyerEntity: $pair->buyerEntity,
                receivableAmountIdr: $ar,
                payableAmountIdr: $ap,
                eliminatedBalanceIdr: $eliminatedBalance,
                eliminatedPnlIdr: $eliminatedPnl,
                discrepancyIdr: $discrepancy - $inTransit,
                inTransitAmountIdr: $inTransit,
                status: $status
            );

            $matchItems[] = $item;
            $totalEliminatedBalance += $eliminatedBalance;
            $totalEliminatedPnl += $eliminatedPnl;
            $totalInTransit += $inTransit;
            $totalDiscrepancy += max(0, $discrepancy - $inTransit);

            $matrixDetails[] = [
                'buyer' => $pair->buyerEntity,
                'discrepancy' => $discrepancy,
                'eliminated_balance' => $eliminatedBalance,
                'in_transit' => $inTransit,
                'seller' => $pair->sellerEntity,
                'status' => $status,
            ];
        }

        $certificate = new BilateralEliminationCertificate(
            periods: $periods,
            matchedPairCount: count($matchItems),
            totalEliminatedBalanceIdr: $totalEliminatedBalance,
            totalEliminatedPnlIdr: $totalEliminatedPnl,
            totalInTransitIdr: $totalInTransit,
            totalDiscrepancyIdr: $totalDiscrepancy,
            matrixDetails: $matrixDetails
        );

        return new BilateralEliminationResultDTO(
            periods: $periods,
            matchedPairCount: count($matchItems),
            totalEliminatedBalanceIdr: $totalEliminatedBalance,
            totalEliminatedPnlIdr: $totalEliminatedPnl,
            totalInTransitIdr: $totalInTransit,
            totalDiscrepancyIdr: $totalDiscrepancy,
            results: $matchItems,
            certificate: $certificate
        );
    }
}
