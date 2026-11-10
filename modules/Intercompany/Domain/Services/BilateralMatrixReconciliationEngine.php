<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Intercompany\Domain\Algorithms\BilateralEliminationMatcher;
use Modules\Intercompany\Domain\DTOs\BilateralBalanceDTO;
use Modules\Intercompany\Domain\DTOs\BilateralEliminationResultDTO;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Psr\Clock\ClockInterface;

/**
 * Engine Rekonsiliasi Matriks Bilateral Antar-Entitas Grup (52.1, 52.4).
 * Melakukan audit menyeluruh atas pasangan piutang (AR) vs utang (AP),
 * mengelompokkan barang dalam perjalanan (in-transit aging),
 * mendeteksi selisih material vs immaterial, dan menghasilkan berkas pembuktian.
 */
class BilateralMatrixReconciliationEngine
{
    public function __construct(
        protected readonly BilateralEliminationMatcher $matcher,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * @return array{
     *     period: string,
     *     generated_at: string,
     *     total_entities: int,
     *     total_pairs_analyzed: int,
     *     balanced_pairs_count: int,
     *     discrepancy_pairs_count: int,
     *     total_ar_minor: int,
     *     total_ap_minor: int,
     *     net_discrepancy_minor: int,
     *     pairs: list<array<string, mixed>>,
     *     in_transit_aging: array{under_30_days: int, days_31_to_60: int, over_60_days: int},
     *     match_result: BilateralEliminationResultDTO
     * }
     */
    public function reconcilePeriod(string $period, int $toleranceMinor = 0): array
    {
        $entities = IntercompanyEntity::where('is_active', true)->orderBy('entity_code')->get();
        $balanceDtos = [];
        $pairDetails = [];

        $totalAr = 0;
        $totalAp = 0;
        $balancedCount = 0;
        $discrepancyCount = 0;

        $inTransitUnder30 = 0;
        $inTransit31To60 = 0;
        $inTransitOver60 = 0;

        $now = $this->now();

        foreach ($entities as $e1) {
            foreach ($entities as $e2) {
                if ($e1->id === $e2->id) {
                    continue;
                }

                $txs = IntercompanyTransaction::where('selling_entity', $e1->entity_code)
                    ->where('buying_entity', $e2->entity_code)
                    ->get();

                if ($txs->isEmpty()) {
                    continue;
                }

                $sellerAr = 0;
                $buyerAp = 0;
                $inTransitForPair = 0;

                foreach ($txs as $tx) {
                    $amount = (int) $tx->amount_idr;

                    if (in_array($tx->status, ['posted_seller', 'posted_buyer', 'reconciled'], true)) {
                        $sellerAr += $amount;
                    }

                    if (in_array($tx->status, ['posted_buyer', 'reconciled'], true)) {
                        $buyerAp += $amount;
                    }

                    // Deteksi in-transit: sudah diposting di penjual tetapi belum di pembeli
                    if ($tx->status === 'posted_seller') {
                        $inTransitForPair += $amount;
                        $daysDiff = $tx->created_at ? $now->diffInDays($tx->created_at) : 0;
                        if ($daysDiff <= 30) {
                            $inTransitUnder30 += $amount;
                        } elseif ($daysDiff <= 60) {
                            $inTransit31To60 += $amount;
                        } else {
                            $inTransitOver60 += $amount;
                        }
                    }
                }

                $diff = $sellerAr - $buyerAp;
                $isBalanced = (abs($diff) <= $toleranceMinor);

                if ($isBalanced) {
                    $balancedCount++;
                } else {
                    $discrepancyCount++;
                }

                $totalAr += $sellerAr;
                $totalAp += $buyerAp;

                $dto = new BilateralBalanceDTO(
                    entityA: $e1->entity_code,
                    entityB: $e2->entity_code,
                    arBalanceMinor: $sellerAr,
                    apBalanceMinor: $buyerAp,
                    currency: 'IDR'
                );
                $balanceDtos[] = $dto;

                $pairDetails[] = [
                    'seller_entity' => $e1->entity_code,
                    'seller_legal_name' => $e1->legal_name,
                    'buyer_entity' => $e2->entity_code,
                    'buyer_legal_name' => $e2->legal_name,
                    'ar_balance_minor' => $sellerAr,
                    'ap_balance_minor' => $buyerAp,
                    'discrepancy_minor' => $diff,
                    'in_transit_minor' => $inTransitForPair,
                    'is_balanced' => $isBalanced,
                    'status' => $isBalanced ? 'ALIGNED' : 'DISCREPANCY',
                    'transactions_count' => $txs->count(),
                ];
            }
        }

        $matchResult = $this->matcher->matchBalances($balanceDtos, $toleranceMinor);

        return [
            'period' => $period,
            'generated_at' => $now->toIso8601String(),
            'total_entities' => $entities->count(),
            'total_pairs_analyzed' => count($pairDetails),
            'balanced_pairs_count' => $balancedCount,
            'discrepancy_pairs_count' => $discrepancyCount,
            'total_ar_minor' => $totalAr,
            'total_ap_minor' => $totalAp,
            'net_discrepancy_minor' => $totalAr - $totalAp,
            'pairs' => $pairDetails,
            'in_transit_aging' => [
                'under_30_days' => $inTransitUnder30,
                'days_31_to_60' => $inTransit31To60,
                'over_60_days' => $inTransitOver60,
            ],
            'match_result' => $matchResult,
        ];
    }
}
