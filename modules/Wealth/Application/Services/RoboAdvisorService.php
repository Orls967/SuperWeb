<?php

declare(strict_types=1);

namespace Modules\Wealth\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class RoboAdvisorService
{
    public function __construct(
        protected SimClockInterface $simClock,
        protected LedgerService $ledgerService
    ) {}

    public function configureProfile(
        int $userId,
        string $riskProfile,
        int $monthlySpendBaselineIdr,
        array $allocationPcts = [50, 30, 20]
    ): WmProfile {
        return DB::transaction(function () use ($userId, $riskProfile, $monthlySpendBaselineIdr, $allocationPcts) {
            // Guardrail: Emergency fund target = at least 2 months baseline spend
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

    public function executeSurplusAutoInvest(WmProfile $profile, int $currentWalletBalanceIdr, string $cycleKey): ?int
    {
        $idempotencyKey = "wm:invest:{$profile->id}:{$cycleKey}";

        return DB::transaction(function () use ($profile, $currentWalletBalanceIdr, $idempotencyKey) {
            // Guardrail: Emergency fund (2x monthly spend) must remain untouched
            $safeFloor = $profile->emergency_fund_target_idr;
            $investableSurplus = $currentWalletBalanceIdr - $safeFloor;

            if ($investableSurplus <= 0) {
                return 0; // Guardrail preserved: nothing invested
            }

            $plan = $profile->plan ?? WmPlan::where('profile_id', $profile->id)->first();
            $mfPct = $plan ? $plan->allocation_mutual_funds_pct : 50;
            $goldPct = $plan ? $plan->allocation_gold_pct : 30;
            $cryptoPct = $plan ? $plan->allocation_crypto_pct : 20;

            $mfAmount = (int) intdiv($investableSurplus * $mfPct, 100);
            $goldAmount = (int) intdiv($investableSurplus * $goldPct, 100);
            $cryptoAmount = $investableSurplus - ($mfAmount + $goldAmount);

            // Update holdings
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

    protected function addHolding(int $profileId, string $assetType, int $amount): void
    {
        $holding = WmHolding::firstOrNew([
            'profile_id' => $profileId,
            'asset_type' => $assetType,
        ]);
        $holding->value_idr += $amount;
        $holding->save();
    }
}
