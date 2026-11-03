<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use UnexpectedValueException;

/**
 * Verifier for License Waterfall distribution calculations and certificates.
 *
 * Enforces PR2 verification:
 * - Conservation of value: gross_revenue == distribution_fee + recouped_advance + sum(allocations)
 * - Advance integrity: remaining_advance == unrecouped_advance - recouped_advance
 * - If unrecouped_advance > recouped_advance, distributable_pool MUST be 0
 * - Net after fee == gross_revenue - distribution_fee
 * - Distributable pool == net_after_fee - recouped_advance
 * - Every allocation >= 0 and sum(allocations) == distributable_pool
 */
final class WaterfallVerifier
{
    /**
     * @param array{
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
     *     certificate?: array<string, mixed>
     * } $result
     *
     * @throws UnexpectedValueException
     */
    public static function verify(array $result, int $unrecoupedAdvanceIdr): bool
    {
        if (! isset(
            $result['gross_revenue'],
            $result['distribution_fee'],
            $result['net_after_fee'],
            $result['recouped_advance'],
            $result['remaining_advance'],
            $result['distributable_pool'],
            $result['allocations']
        )) {
            throw new UnexpectedValueException('Missing required fields in Waterfall result');
        }

        $gross = $result['gross_revenue'];
        $fee = $result['distribution_fee'];
        $netAfterFee = $result['net_after_fee'];
        $recouped = $result['recouped_advance'];
        $remaining = $result['remaining_advance'];
        $pool = $result['distributable_pool'];
        $allocations = $result['allocations'];

        if ($gross < 0 || $fee < 0 || $netAfterFee < 0 || $recouped < 0 || $remaining < 0 || $pool < 0) {
            throw new UnexpectedValueException('Waterfall cannot contain negative monetary balances');
        }

        if ($netAfterFee !== $gross - $fee) {
            throw new UnexpectedValueException("Net after fee mismatch: {$netAfterFee} != {$gross} - {$fee}");
        }

        if ($pool !== $netAfterFee - $recouped) {
            throw new UnexpectedValueException("Distributable pool mismatch: {$pool} != {$netAfterFee} - {$recouped}");
        }

        if ($remaining !== $unrecoupedAdvanceIdr - $recouped) {
            throw new UnexpectedValueException("Remaining advance mismatch: {$remaining} != {$unrecoupedAdvanceIdr} - {$recouped}");
        }

        if ($remaining > 0 && $pool !== 0) {
            throw new UnexpectedValueException("Distributable pool ({$pool}) must be 0 while advance remains unrecouped ({$remaining})");
        }

        $sumAllocations = 0;
        $totalShares = 0;
        foreach ($allocations as $idx => $alloc) {
            if ($alloc['amount'] < 0) {
                throw new UnexpectedValueException("Negative allocation {$alloc['amount']} for rightsholder {$alloc['id']}");
            }
            if ($alloc['share_bps'] < 0) {
                throw new UnexpectedValueException("Negative share_bps at index {$idx}");
            }
            $sumAllocations += $alloc['amount'];
            $totalShares += $alloc['share_bps'];
        }

        if ($totalShares !== 10000) {
            throw new UnexpectedValueException("Total allocation share_bps must equal 10000, got {$totalShares}");
        }

        if ($sumAllocations !== $pool) {
            throw new UnexpectedValueException("Allocations sum ({$sumAllocations}) does not equal distributable pool ({$pool})");
        }

        // Strict conservation of value check
        $totalAccounted = $fee + $recouped + $sumAllocations;
        if ($totalAccounted !== $gross) {
            throw new UnexpectedValueException("Conservation of value violated: gross {$gross} != fee {$fee} + recouped {$recouped} + distributed {$sumAllocations} (sum: {$totalAccounted})");
        }

        return true;
    }
}
