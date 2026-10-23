<?php

declare(strict_types=1);

namespace Modules\Rwa\database\seeders;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaHolding;

class RwaDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $investor = User::firstOrCreate(
            ['email' => 'rwa_investor@example.com'],
            ['name' => 'RWA Investor', 'password' => bcrypt('secret123')]
        );

        // Ensure user wallet exists in ledger
        LedgerAccount::firstOrCreate(
            ['code' => "wallet:user:{$investor->id}:IDR"],
            [
                'name' => "User {$investor->id} Wallet IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $asset = RwaAsset::firstOrCreate(
            ['token_symbol' => 'RWA-MALL01'],
            [
                'name' => 'Grand Duta Commercial Complex Tower A',
                'underlying_asset_type' => 'COMMERCIAL_PROPERTY',
                'underlying_asset_id' => 'BLD-DUTA-01',
                'appraisal_value_idr' => 10000000000,
                'total_supply_tokens' => 10000,
                'token_price_idr' => 1000000,
                'status' => 'active',
            ]
        );

        // Ensure pool account exists
        LedgerAccount::firstOrCreate(
            ['code' => "rwa:pool:{$asset->token_symbol}:IDR"],
            [
                'name' => "RWA Pool {$asset->token_symbol} IDR",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        RwaHolding::firstOrCreate(
            ['rwa_asset_id' => $asset->id, 'user_id' => $investor->id],
            [
                'token_balance' => 2500,
            ]
        );
    }
}
