<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use RuntimeException;

class UniversalGroupCashPoolingService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    /**
     * Sweep daily idle cash above operational buffer to central Treasury yield pool
     *
     * @return array{entity_id: string, swept_amount_idr: int, remaining_buffer_idr: int, status: string}
     */
    public function sweepEntityExcessCash(
        string $entityId,
        int $closingBalanceIdr,
        int $operationalFloatTargetIdr,
        string $dateYmd = ''
    ): array {
        if ($closingBalanceIdr < 0 || $operationalFloatTargetIdr < 0) {
            throw new RuntimeException('Balances must be non-negative.');
        }

        $sweepAmount = max(0, $closingBalanceIdr - $operationalFloatTargetIdr);
        $date = $dateYmd !== '' ? $dateYmd : date('Y-m-d');

        if ($sweepAmount > 0) {
            $idempotencyKey = 'cash_sweep:'.hash('sha256', "{$entityId}:{$date}:{$sweepAmount}");

            // Double-entry: Debit central yield pool, Credit intercompany sweep liability
            $this->ledger->post(new PostingDTO(
                type: TransactionType::SETTLEMENT->value,
                description: "Overnight liquidity cash sweep for entity {$entityId} on {$date}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode('treasury:yield_pool:IDR', 'IDR', -$sweepAmount),
                    PostingEntryDTO::forCode('treasury:intercompany_sweep:default:IDR', 'IDR', $sweepAmount),
                ],
                referenceType: 'cash_pooling_sweeps',
                referenceId: (string) hash('crc32b', "{$entityId}:{$date}"),
            ));
        }

        return [
            'entity_id' => $entityId,
            'swept_amount_idr' => $sweepAmount,
            'remaining_buffer_idr' => min($closingBalanceIdr, $operationalFloatTargetIdr),
            'status' => $sweepAmount > 0 ? 'SWEPT_TO_YIELD_POOL' : 'NO_EXCESS_FLOAT',
        ];
    }

    /**
     * Compute and distribute daily overnight money market yield across pooled entities
     *
     * @param  array<string, int>  $entityContributionMap  entityId => balanceIdr
     * @return array{total_pool_idr: int, total_yield_idr: int, distributions: array<string, int>}
     */
    public function calculateOvernightYieldDistribution(
        array $entityContributionMap,
        int $annualYieldBps = 550 // 5.50% p.a.
    ): array {
        $totalPoolIdr = array_sum($entityContributionMap);
        if ($totalPoolIdr <= 0) {
            return [
                'total_pool_idr' => 0,
                'total_yield_idr' => 0,
                'distributions' => [],
            ];
        }

        // Daily yield = (Pool * BPS) / (10000 * 365)
        $totalDailyYield = (int) floor(($totalPoolIdr * $annualYieldBps) / (10000 * 365));
        $distributions = [];

        foreach ($entityContributionMap as $entityId => $contrib) {
            $entityYield = (int) floor(($totalDailyYield * $contrib) / $totalPoolIdr);
            $distributions[$entityId] = $entityYield;
        }

        return [
            'total_pool_idr' => $totalPoolIdr,
            'total_yield_idr' => $totalDailyYield,
            'distributions' => $distributions,
        ];
    }
}
