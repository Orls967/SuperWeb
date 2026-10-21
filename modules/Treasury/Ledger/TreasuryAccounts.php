<?php

declare(strict_types=1);

namespace Modules\Treasury\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Treasury.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class TreasuryAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'TRS_USD_SRC',
                'name' => 'USD Source Account',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => true,
            ],
            [
                'code' => 'TRS_USD_DEST',
                'name' => 'USD Destination Account',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'USD',
                'allow_negative' => true,
            ],
            [
                'code' => 'treasury:yield_pool:IDR',
                'name' => 'Central Treasury Yield Pool IDR',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'treasury:intercompany_sweep:default:IDR',
                'name' => 'Treasury Intercompany Sweep Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
