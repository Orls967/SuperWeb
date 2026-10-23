<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmOrder;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class RebalancePortfolioDriftIdempotentAction extends BaseAction
{
    /**
     * Detect allocation drift and execute idempotent portfolio rebalancing orders.
     *
     * @return array{profile_id: int, total_portfolio_idr: int, rebalanced: bool, orders_count: int, orders: array<WmOrder>}
     */
    public function handle(int $profileId, int $driftThresholdPct = 5): array
    {
        return $this->transaction(function () use ($profileId, $driftThresholdPct) {
            $profile = WmProfile::where('id', $profileId)->firstOrFail();
            $plan = WmPlan::where('profile_id', $profileId)->firstOrFail();

            $holdings = WmHolding::where('profile_id', $profileId)->get()->keyBy('asset_type');
            $totalPortfolioIdr = $holdings->sum('value_idr');

            if ($totalPortfolioIdr <= 0) {
                return [
                    'profile_id' => $profileId,
                    'total_portfolio_idr' => 0,
                    'rebalanced' => false,
                    'orders_count' => 0,
                    'orders' => [],
                ];
            }

            $targets = [
                'mutual_funds' => (int) round(($totalPortfolioIdr * $plan->allocation_mutual_funds_pct) / 100),
                'gold' => (int) round(($totalPortfolioIdr * $plan->allocation_gold_pct) / 100),
                'crypto' => (int) round(($totalPortfolioIdr * $plan->allocation_crypto_pct) / 100),
            ];

            $orders = [];
            $driftDetected = false;

            foreach ($targets as $asset => $targetAmount) {
                $currentAmount = $holdings->get($asset)?->value_idr ?? 0;
                $currentPct = (int) round(($currentAmount / $totalPortfolioIdr) * 100);
                $targetPct = match ($asset) {
                    'mutual_funds' => $plan->allocation_mutual_funds_pct,
                    'gold' => $plan->allocation_gold_pct,
                    default => $plan->allocation_crypto_pct,
                };

                if (abs($currentPct - $targetPct) >= $driftThresholdPct) {
                    $driftDetected = true;
                }
            }

            if ($driftDetected) {
                foreach ($targets as $asset => $targetAmount) {
                    $currentAmount = $holdings->get($asset)?->value_idr ?? 0;
                    $diffAmount = $targetAmount - $currentAmount;

                    if ($diffAmount !== 0) {
                        $idempotencyKey = hash('sha256', "wm:rebalance:{$profileId}:{$asset}:{$targetAmount}:".date('Ymd'));

                        $order = WmOrder::firstOrCreate(
                            ['idempotency_key' => $idempotencyKey],
                            [
                                'profile_id' => $profileId,
                                'asset_type' => $asset,
                                'amount_idr' => abs($diffAmount),
                                'status' => 'executed',
                                'executed_at' => now(),
                            ]
                        );

                        WmHolding::updateOrCreate(
                            ['profile_id' => $profileId, 'asset_type' => $asset],
                            ['value_idr' => $targetAmount]
                        );

                        $orders[] = $order;
                    }
                }

                $this->audit(
                    action: 'wealth.portfolio.rebalanced',
                    auditable: $profile,
                    oldValues: null,
                    newValues: [
                        'total_portfolio_idr' => $totalPortfolioIdr,
                        'orders_executed' => count($orders),
                    ],
                    correlationId: "wm_rebal_{$profileId}_".date('Ymd'),
                    impactType: 'portfolio_rebalancing'
                );
            }

            return [
                'profile_id' => $profileId,
                'total_portfolio_idr' => $totalPortfolioIdr,
                'rebalanced' => $driftDetected,
                'orders_count' => count($orders),
                'orders' => $orders,
            ];
        });
    }
}
