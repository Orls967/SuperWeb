<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * License Revenue Waterfall Distributor.
 *
 * Implements a strict waterfall calculation:
 * 1. Distribution fee in basis points deducted upfront: round_half_up(gross * fee_bps / 10000)
 * 2. Advance recoupment from net revenue: min(net_after_fee, unrecouped_advance)
 * 3. Remaining distributable pool divided among rightsholders via Hamilton largest-remainder apportionment.
 *
 * Guaranteed value conservation:
 * gross_revenue == distribution_fee + recouped_advance + sum(rightsholder_allocations)
 *
 * Complexity: O(R log R) where R is the number of rightsholders.
 */
final class Waterfall
{
    /**
     * @param  int  $grossRevenueIdr  Gross license revenue in IDR (>= 0)
     * @param  int  $distributionFeeBps  Distribution fee in basis points (0..10000)
     * @param  int  $unrecoupedAdvanceIdr  Unrecouped advance balance in IDR (>= 0)
     * @param  list<array{id: string|int, share_bps: int}>  $rightsholders  Rightsholders with shares summing to 10,000 bps
     * @return array{
     *     gross_revenue: int,
     *     distribution_fee: int,
     *     net_after_fee: int,
     *     recouped_advance: int,
     *     remaining_advance: int,
     *     distributable_pool: int,
     *     allocations: list<array{
     *         id: string|int,
     *         share_bps: int,
     *         amount: int
     *     }>,
     *     certificate: array{
     *         gross_revenue: int,
     *         distribution_fee: int,
     *         net_after_fee: int,
     *         recouped_advance: int,
     *         remaining_advance: int,
     *         distributable_pool: int,
     *         rightsholders_count: int,
     *         allocations: list<array{
     *             id: string|int,
     *             share_bps: int,
     *             amount: int
     *         }>
     *     }
     * }
     */
    public static function calculate(
        int $grossRevenueIdr,
        int $distributionFeeBps,
        int $unrecoupedAdvanceIdr,
        array $rightsholders,
    ): array {
        if ($grossRevenueIdr < 0) {
            throw new InvalidArgumentException("Gross revenue cannot be negative, got: {$grossRevenueIdr}");
        }
        if ($distributionFeeBps < 0 || $distributionFeeBps > 10000) {
            throw new InvalidArgumentException("Distribution fee bps must be within [0, 10000], got: {$distributionFeeBps}");
        }
        if ($unrecoupedAdvanceIdr < 0) {
            throw new InvalidArgumentException("Unrecouped advance cannot be negative, got: {$unrecoupedAdvanceIdr}");
        }
        if ($rightsholders === []) {
            throw new InvalidArgumentException('Rightsholders list cannot be empty');
        }

        $totalSharesBps = 0;
        foreach ($rightsholders as $idx => $r) {
            if (! isset($r['id'], $r['share_bps'])) {
                throw new InvalidArgumentException("Missing required fields in rightsholder at index {$idx}");
            }
            if ($r['share_bps'] < 0) {
                throw new InvalidArgumentException("Rightsholder share cannot be negative at index {$idx}, got: {$r['share_bps']}");
            }
            $totalSharesBps += $r['share_bps'];
        }

        if ($totalSharesBps !== 10000) {
            throw new InvalidArgumentException("Sum of rightsholder shares must equal exactly 10,000 bps, got: {$totalSharesBps}");
        }

        // Step 1: Distribution fee (half-up integer rounding)
        $distributionFee = intdiv($grossRevenueIdr * $distributionFeeBps + 5000, 10000);
        $netAfterFee = $grossRevenueIdr - $distributionFee;

        // Step 2: Recoupment of advance
        $recoupedAdvance = min($netAfterFee, $unrecoupedAdvanceIdr);
        $remainingAdvance = $unrecoupedAdvanceIdr - $recoupedAdvance;
        $distributablePool = $netAfterFee - $recoupedAdvance;

        // Step 3: Hamilton apportionment of distributable pool
        $weights = [];
        foreach ($rightsholders as $r) {
            $weights[$r['id']] = $r['share_bps'];
        }

        $apportioned = HamiltonApportionment::apportion($distributablePool, $weights);

        $allocations = [];
        foreach ($rightsholders as $r) {
            $allocations[] = [
                'id' => $r['id'],
                'share_bps' => $r['share_bps'],
                'amount' => $apportioned[$r['id']],
            ];
        }

        $certificate = [
            'gross_revenue' => $grossRevenueIdr,
            'distribution_fee' => $distributionFee,
            'net_after_fee' => $netAfterFee,
            'recouped_advance' => $recoupedAdvance,
            'remaining_advance' => $remainingAdvance,
            'distributable_pool' => $distributablePool,
            'rightsholders_count' => count($rightsholders),
            'allocations' => $allocations,
        ];

        $result = [
            'gross_revenue' => $grossRevenueIdr,
            'distribution_fee' => $distributionFee,
            'net_after_fee' => $netAfterFee,
            'recouped_advance' => $recoupedAdvance,
            'remaining_advance' => $remainingAdvance,
            'distributable_pool' => $distributablePool,
            'allocations' => $allocations,
            'certificate' => $certificate,
        ];

        WaterfallVerifier::verify($result, $unrecoupedAdvanceIdr);

        return $result;
    }
}
