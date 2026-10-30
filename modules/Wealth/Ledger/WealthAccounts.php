<?php

declare(strict_types=1);

namespace Modules\Wealth\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Wealth.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 * Mematuhi U-3: allow_negative = false kecuali clearing/escrow.
 */
final class WealthAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'treasury:pool:IDR',
                'name' => 'Treasury Pool',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'wm:management_fee:IDR',
                'name' => 'Wealth Management Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'wm:pool:default:IDR',
                'name' => 'Wealth Holdings Default Pool',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'wm:cash_sweep_clearing:IDR',
                'name' => 'Wealth Cash Sweep Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'wm:portfolio_custody:default:IDR',
                'name' => 'Wealth Portfolio Custody Default',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function cashSweepClearing(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'wm:cash_sweep_clearing:IDR'],
            [
                'name' => 'Wealth Cash Sweep Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
    }

    public static function managementFeeRevenue(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'wm:management_fee:IDR'],
            [
                'name' => 'Wealth Management Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ]
        );
    }

    public static function portfolioCustody(int|string $profileId = 'default'): LedgerAccount
    {
        $code = "wm:portfolio_custody:{$profileId}:IDR";

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "Wealth Portfolio Custody {$profileId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ]
        );
    }
}
