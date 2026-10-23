<?php

declare(strict_types=1);

namespace Modules\Wealth\database\seeders;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Wealth\Domain\Models\WmHolding;
use Modules\Wealth\Domain\Models\WmPlan;
use Modules\Wealth\Domain\Models\WmProfile;

class WealthDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $user = User::firstOrCreate(
            ['email' => 'wm_investor@example.com'],
            ['name' => 'Wealth Investor', 'password' => bcrypt('secret123')]
        );

        // Ensure user wallet exists
        LedgerAccount::firstOrCreate(
            ['code' => "wallet:user:{$user->id}:IDR"],
            [
                'name' => "User {$user->id} Wallet IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $profile = WmProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'risk_profile' => 'moderate',
                'monthly_spend_baseline_idr' => 10000000,
                'emergency_fund_target_idr' => 20000000,
                'auto_invest_enabled' => true,
            ]
        );

        WmPlan::firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'allocation_mutual_funds_pct' => 50,
                'allocation_gold_pct' => 30,
                'allocation_crypto_pct' => 20,
            ]
        );

        WmHolding::firstOrCreate(
            ['profile_id' => $profile->id, 'asset_type' => 'mutual_fund'],
            ['value_idr' => 15000000]
        );
    }
}
