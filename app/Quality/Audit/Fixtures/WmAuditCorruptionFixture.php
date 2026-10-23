<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

final class WmAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'wm:audit';
    }

    public function corrupt(): string
    {
        $user = User::factory()->create();

        $profile = WmProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'aggressive',
            'monthly_spend_baseline_idr' => 15000000,
            'emergency_fund_target_idr' => 30000000,
            'auto_invest_enabled' => true,
        ]);

        // Corrupted plan: allocations sum to 70% + 50% + 20% = 140% (exceeds 100%!)
        WmPlan::create([
            'profile_id' => $profile->id,
            'allocation_mutual_funds_pct' => 70,
            'allocation_gold_pct' => 50,
            'allocation_crypto_pct' => 20,
        ]);

        return 'Wealth management audit FAILED';
    }
}
