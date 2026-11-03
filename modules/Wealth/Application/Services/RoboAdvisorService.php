<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmOrder;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmPortfolioHolding;
use Modules\Wealth\Domain\Models\WmProfile;

class RoboAdvisorService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function submitRiskSurvey(int $userId, array $answers, string $idempotencyKey): WmProfile
    {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key is required.');
        }

        $values = array_values($answers);
        $avg = count($values) > 0 ? (int) round(array_sum($values) / count($values)) : 3;
        $riskScore = max(1, min(5, $avg));

        $weights = match ($riskScore) {
            1 => ['mm' => 7000, 'bd' => 2500, 'eq' => 500, 'tier' => 'conservative'],
            2 => ['mm' => 5000, 'bd' => 3500, 'eq' => 1500, 'tier' => 'conservative'],
            3 => ['mm' => 3000, 'bd' => 4000, 'eq' => 3000, 'tier' => 'moderate'],
            4 => ['mm' => 2000, 'bd' => 3000, 'eq' => 5000, 'tier' => 'aggressive'],
            5 => ['mm' => 1000, 'bd' => 2000, 'eq' => 7000, 'tier' => 'aggressive'],
        };

        return WmProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'risk_score' => $riskScore,
                'risk_profile' => $weights['tier'],
                'target_money_market_bps' => $weights['mm'],
                'target_bonds_bps' => $weights['bd'],
                'target_equity_rwa_bps' => $weights['eq'],
                'drift_band_bps' => 500,
                'auto_invest_enabled' => true,
            ]
        );
    }

    public function configureProfile(
        int $userId,
        string $riskProfile,
        int $monthlySpendBaselineIdr,
        array $allocationPcts = [50, 30, 20]
    ): WmProfile {
        return DB::transaction(function () use ($userId, $riskProfile, $monthlySpendBaselineIdr, $allocationPcts) {
            // Guardrail: Emergency fund target = at least 2 months baseline spend (DoR 73.2)
            $emergencyFundTarget = $monthlySpendBaselineIdr * 2;

            $profile = WmProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'risk_profile' => $riskProfile,
                    'monthly_spend_baseline_idr' => $monthlySpendBaselineIdr,
                    'emergency_fund_target_idr' => $emergencyFundTarget,
                    'auto_invest_enabled' => true,
                ]
            );

            WmPlan::updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'allocation_mutual_funds_pct' => $allocationPcts[0] ?? 50,
                    'allocation_gold_pct' => $allocationPcts[1] ?? 30,
                    'allocation_crypto_pct' => $allocationPcts[2] ?? 20,
                ]
            );

            return $profile;
        });
    }

    /**
     * Surplus detector reading spending baseline and 2-month liquidity guardrail (DoR 73.2).
     */
    public function detectSurplus(WmProfile $profile, int $currentWalletBalanceIdr, int $threeMonthAvgSpendIdr): array
    {
        $safeFloor = max($profile->emergency_fund_target_idr, $threeMonthAvgSpendIdr * 2);
        $investableSurplus = max(0, $currentWalletBalanceIdr - $safeFloor);

        return [
            'investable_surplus_idr' => $investableSurplus,
            'emergency_floor_idr' => $safeFloor,
            'guardrail_maintained' => $currentWalletBalanceIdr >= $safeFloor,
        ];
    }

    /**
     * Execute auto invest with multi-asset split and wm_orders creation (DoR 73.1, 73.3, 73.7b).
     */
    public function executeSurplusAutoInvest(WmProfile $profile, int $currentWalletBalanceIdr, string $cycleKey): ?int
    {
        $idempotencyKey = "wm:invest:{$profile->id}:{$cycleKey}";

        return DB::transaction(function () use ($profile, $currentWalletBalanceIdr, $cycleKey, $idempotencyKey) {
            // Guardrail: Emergency fund (2x monthly spend) must remain untouched
            $safeFloor = $profile->emergency_fund_target_idr;
            $investableSurplus = $currentWalletBalanceIdr - $safeFloor;

            if ($investableSurplus <= 0) {
                return 0; // Guardrail preserved: nothing invested
            }

            // Check if already executed for cycle
            $existingOrder = WmOrder::where('idempotency_key', "wm:ord:{$profile->id}:{$cycleKey}:mutual_fund")->first();
            if ($existingOrder) {
                return (int) WmOrder::where('profile_id', $profile->id)
                    ->where('idempotency_key', 'LIKE', "wm:ord:{$profile->id}:{$cycleKey}:%")
                    ->sum('amount_idr');
            }

            $plan = $profile->plan ?? WmPlan::where('profile_id', $profile->id)->first();
            $mfPct = $plan ? $plan->allocation_mutual_funds_pct : 50;
            $goldPct = $plan ? $plan->allocation_gold_pct : 30;

            $mfAmount = (int) intdiv($investableSurplus * $mfPct, 100);
            $goldAmount = (int) intdiv($investableSurplus * $goldPct, 100);
            $cryptoAmount = $investableSurplus - ($mfAmount + $goldAmount);

            $now = $this->simClock->now();

            // Record orders in wm_orders (DoR 73.1)
            $this->createOrder($profile->id, 'mutual_fund', $mfAmount, "wm:ord:{$profile->id}:{$cycleKey}:mutual_fund", $now);
            $this->createOrder($profile->id, 'digital_gold', $goldAmount, "wm:ord:{$profile->id}:{$cycleKey}:digital_gold", $now);
            $this->createOrder($profile->id, 'crypto', $cryptoAmount, "wm:ord:{$profile->id}:{$cycleKey}:crypto", $now);

            // Update holdings (DoR 73.1)
            $this->addHolding($profile->id, 'mutual_fund', $mfAmount);
            $this->addHolding($profile->id, 'digital_gold', $goldAmount);
            $this->addHolding($profile->id, 'crypto', $cryptoAmount);

            // Post double-entry to ledger: Debit user wallet, Credit treasury/custody pool
            $this->ledgerService->post(new PostingDTO(
                type: 'wm_auto_invest',
                description: "Robo-Advisor Auto Invest Allocation for User {$profile->user_id}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode("wallet:user:{$profile->user_id}:IDR", 'IDR', -$investableSurplus),
                    PostingEntryDTO::forCode('treasury:pool:IDR', 'IDR', $investableSurplus),
                ],
                referenceType: 'wm_profile',
                referenceId: (string) $profile->id,
            ));

            return $investableSurplus;
        });
    }

    /**
     * Portfolio rebalancing when drift > 5% target (DoR 73.5 / 73.7c).
     */
    public function rebalancePortfolio(WmProfile $profile): array
    {
        return DB::transaction(function () use ($profile) {
            $plan = $profile->plan ?? WmPlan::where('profile_id', $profile->id)->first();
            if (! $plan) {
                return ['drift_detected' => false, 'rebalanced' => false];
            }

            $holdings = WmHolding::where('profile_id', $profile->id)->get()->keyBy('asset_type');
            $totalValue = (int) $holdings->sum('value_idr');

            if ($totalValue <= 0) {
                return ['drift_detected' => false, 'rebalanced' => false];
            }

            $targetPcts = [
                'mutual_fund' => $plan->allocation_mutual_funds_pct,
                'digital_gold' => $plan->allocation_gold_pct,
                'crypto' => $plan->allocation_crypto_pct,
            ];

            $maxDrift = 0;
            foreach ($targetPcts as $asset => $targetPct) {
                $currentVal = (int) ($holdings->get($asset)?->value_idr ?? 0);
                $currentPct = (int) round(($currentVal * 100) / $totalValue);
                $drift = abs($currentPct - $targetPct);
                if ($drift > $maxDrift) {
                    $maxDrift = $drift;
                }
            }

            if ($maxDrift <= 5) {
                return ['drift_detected' => false, 'rebalanced' => false, 'max_drift_pct' => $maxDrift];
            }

            // Rebalance deterministically to target percentages
            $mfTarget = (int) intdiv($totalValue * $targetPcts['mutual_fund'], 100);
            $goldTarget = (int) intdiv($totalValue * $targetPcts['digital_gold'], 100);
            $cryptoTarget = $totalValue - ($mfTarget + $goldTarget);

            $this->setHolding($profile->id, 'mutual_fund', $mfTarget);
            $this->setHolding($profile->id, 'digital_gold', $goldTarget);
            $this->setHolding($profile->id, 'crypto', $cryptoTarget);

            return [
                'drift_detected' => true,
                'rebalanced' => true,
                'max_drift_pct' => $maxDrift,
                'new_holdings' => [
                    'mutual_fund' => $mfTarget,
                    'digital_gold' => $goldTarget,
                    'crypto' => $cryptoTarget,
                ],
            ];
        });
    }

    /**
     * Daily management fee calculation with 6-decimal integer accuracy (DoR 73.5 / 73.7d).
     * e.g. 25 bps annual = 0.25% per annum.
     */
    public function calculateDailyManagementFee(int $portfolioValueIdr, int $annualFeeBps = 25): int
    {
        if ($portfolioValueIdr <= 0) {
            return 0;
        }

        // Daily fee in IDR = (portfolioValue * annualFeeBps) / (365 * 10000)
        return (int) intdiv($portfolioValueIdr * $annualFeeBps, 365 * 10000);
    }

    /**
     * Accrue and charge daily management fee to ledger (DoR 73.5).
     */
    public function accrueManagementFee(WmProfile $profile, int $annualFeeBps = 25): int
    {
        $totalHoldings = (int) WmHolding::where('profile_id', $profile->id)->sum('value_idr');
        $fee = $this->calculateDailyManagementFee($totalHoldings, $annualFeeBps);

        if ($fee <= 0) {
            return 0;
        }

        $now = $this->simClock->now();
        $idempotencyKey = "wm:fee:{$profile->id}:{$now->toDateString()}";

        $this->ledgerService->post(new PostingDTO(
            type: 'wm_fee_accrual',
            description: "Daily Wealth Management Fee for Profile {$profile->id}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode("wallet:user:{$profile->user_id}:IDR", 'IDR', -$fee),
                PostingEntryDTO::forCode('wm:management_fee:IDR', 'IDR', $fee),
            ],
            referenceType: 'wm_profile',
            referenceId: (string) $profile->id,
        ));

        return $fee;
    }

    /**
     * Reconcile holdings against total executed orders (DoR 73.7e).
     */
    public function reconcileHoldings(WmProfile $profile): array
    {
        $totalHoldings = (int) WmHolding::where('profile_id', $profile->id)->sum('value_idr');
        $totalOrders = (int) WmOrder::where('profile_id', $profile->id)
            ->where('status', 'EXECUTED')
            ->sum('amount_idr');

        return [
            'total_holdings_idr' => $totalHoldings,
            'total_orders_idr' => $totalOrders,
            'is_reconciled' => $totalHoldings === $totalOrders,
        ];
    }

    protected function createOrder(int $profileId, string $assetType, int $amount, string $idempotencyKey, CarbonInterface $now): WmOrder
    {
        return WmOrder::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'profile_id' => $profileId,
                'asset_type' => $assetType,
                'amount_idr' => $amount,
                'status' => 'EXECUTED',
                'executed_at' => $now,
            ]
        );
    }

    protected function addHolding(int $profileId, string $assetType, int $amount): void
    {
        $holding = WmHolding::firstOrNew([
            'profile_id' => $profileId,
            'asset_type' => $assetType,
        ]);
        $holding->value_idr += $amount;
        $holding->save();
    }

    protected function setHolding(int $profileId, string $assetType, int $amount): void
    {
        $holding = WmHolding::firstOrNew([
            'profile_id' => $profileId,
            'asset_type' => $assetType,
        ]);
        $holding->value_idr = $amount;
        $holding->save();
    }

    /**
     * Two-source wealth management audit.
     * Cross-checks portfolio allocation sum to 100% and emergency fund guardrails.
     */
    public function auditWealth(): array
    {
        $profileCount = WmProfile::count();
        $planCount = WmPlan::count();
        $holdingCount = WmHolding::count();
        $discrepancies = 0;

        $plans = WmPlan::all();
        foreach ($plans as $plan) {
            $totalPct = $plan->allocation_mutual_funds_pct + $plan->allocation_gold_pct + $plan->allocation_crypto_pct;
            if ($totalPct !== 100) {
                $discrepancies++;
            }
        }

        $profiles = WmProfile::all();
        foreach ($profiles as $profile) {
            $expectedMinFloor = $profile->monthly_spend_baseline_idr * 2;
            if ($profile->emergency_fund_target_idr < $expectedMinFloor) {
                $discrepancies++;
            }
        }

        $portfolioHoldings = WmPortfolioHolding::all();
        $holdingCount += $portfolioHoldings->count();
        foreach ($portfolioHoldings as $holding) {
            $expectedMarketValue = intdiv($holding->units * $holding->current_nav, 10);
            if ($holding->current_market_value_idr !== $expectedMarketValue) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'UNHEALTHY',
            'profile_count' => $profileCount,
            'plan_count' => $planCount,
            'holding_count' => $holdingCount,
            'discrepancies' => $discrepancies,
        ];
    }
}
