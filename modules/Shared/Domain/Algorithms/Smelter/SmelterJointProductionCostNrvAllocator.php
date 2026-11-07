<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Smelter;

/**
 * Algorithm M14: Smelter Joint Production Cost Net Realizable Value (NRV) Allocator.
 *
 * Proportionately distributes joint pyrometallurgical production expenses across the primary
 * product (Ferronickel/NPI/Matte) and secondary industrial byproducts (slag, sulfur, dust)
 * ensuring the joint clearing account strictly balances to zero.
 */
final class SmelterJointProductionCostNrvAllocator
{
    public function allocate(SmelterJointCostInput $input): SmelterJointCostAllocationResult
    {
        // Calculate Net Realizable Value (NRV) for each product stream
        $primaryNrv = (float) ($input->primaryMetalOutputTons * $input->primaryMetalMarketPriceIdr);
        $slagNrv = (float) ($input->granulatedSlagOutputTons * $input->granulatedSlagMarketPriceIdr);
        $sulfurNrv = (float) ($input->crudeSulfurOutputTons * $input->crudeSulfurMarketPriceIdr);
        $dustNrv = (float) ($input->metalFlueDustOutputTons * $input->metalFlueDustMarketPriceIdr);

        $totalNrv = $primaryNrv + $slagNrv + $sulfurNrv + $dustNrv;
        if ($totalNrv <= 0.0) {
            $totalNrv = 1.0;
        }

        // Relative NRV weights
        $primaryWeight = $primaryNrv / $totalNrv;
        $slagWeight = $slagNrv / $totalNrv;
        $sulfurWeight = $sulfurNrv / $totalNrv;
        $dustWeight = $dustNrv / $totalNrv;

        $totalJointCost = $input->jointSmeltingCostIdr;

        $primaryCost = (int) round($totalJointCost * $primaryWeight);
        $slagCost = (int) round($totalJointCost * $slagWeight);
        $sulfurCost = (int) round($totalJointCost * $sulfurWeight);

        // Plug the residual rounding diff to primary metal to guarantee zero ledger clearing imbalance
        $byproductsCost = $slagCost + $sulfurCost;
        $dustCost = (int) round($totalJointCost * $dustWeight);
        $byproductsCost += $dustCost;

        $clearingDiscrepancy = $totalJointCost - ($primaryCost + $byproductsCost);
        $adjustedPrimaryCost = $primaryCost + $clearingDiscrepancy;

        $primaryUnitCost = $input->primaryMetalOutputTons > 0.0
            ? (int) round($adjustedPrimaryCost / $input->primaryMetalOutputTons)
            : 0;

        $productAllocations = [
            'PRIMARY_NPI_METAL' => [
                'output_tons' => $input->primaryMetalOutputTons,
                'market_value_idr' => (int) $primaryNrv,
                'nrv_share_pct' => round($primaryWeight * 100, 2),
                'allocated_cost_idr' => $adjustedPrimaryCost,
                'unit_cost_per_ton_idr' => $primaryUnitCost,
            ],
            'GRANULATED_SLAG' => [
                'output_tons' => $input->granulatedSlagOutputTons,
                'market_value_idr' => (int) $slagNrv,
                'nrv_share_pct' => round($slagWeight * 100, 2),
                'allocated_cost_idr' => $slagCost,
                'unit_cost_per_ton_idr' => $input->granulatedSlagOutputTons > 0.0 ? (int) round($slagCost / $input->granulatedSlagOutputTons) : 0,
            ],
            'CRUDE_SULFUR' => [
                'output_tons' => $input->crudeSulfurOutputTons,
                'market_value_idr' => (int) $sulfurNrv,
                'nrv_share_pct' => round($sulfurWeight * 100, 2),
                'allocated_cost_idr' => $sulfurCost,
                'unit_cost_per_ton_idr' => $input->crudeSulfurOutputTons > 0.0 ? (int) round($sulfurCost / $input->crudeSulfurOutputTons) : 0,
            ],
            'FLUE_DUST' => [
                'output_tons' => $input->metalFlueDustOutputTons,
                'market_value_idr' => (int) $dustNrv,
                'nrv_share_pct' => round($dustWeight * 100, 2),
                'allocated_cost_idr' => $dustCost,
                'unit_cost_per_ton_idr' => $input->metalFlueDustOutputTons > 0.0 ? (int) round($dustCost / $input->metalFlueDustOutputTons) : 0,
            ],
        ];

        $isBalanced = ($adjustedPrimaryCost + $byproductsCost) === $totalJointCost;

        $verificationHash = hash('sha256', sprintf(
            '%d:%d:%d:%d:%d',
            $totalJointCost,
            $adjustedPrimaryCost,
            $byproductsCost,
            $primaryUnitCost,
            $isBalanced ? 1 : 0
        ));

        return new SmelterJointCostAllocationResult(
            totalJointCostAllocatedIdr: $totalJointCost,
            primaryMetalAllocatedCostIdr: $adjustedPrimaryCost,
            primaryMetalUnitCostPerTonIdr: $primaryUnitCost,
            byproductsAllocatedCostIdr: $byproductsCost,
            clearingDiscrepancyIdr: 0,
            productAllocations: $productAllocations,
            isConservationBalanced: $isBalanced,
            verificationHash: $verificationHash
        );
    }
}
