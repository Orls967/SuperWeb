<?php

declare(strict_types=1);

namespace Modules\Insurance\Ledger;

use Illuminate\Support\Str;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Insurance.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 * Mematuhi U-3: allow_negative = false kecuali clearing/escrow.
 */
final class InsuranceAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'ins:premium_reserve:IDR',
                'name' => 'Insurance Premium Reserve',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:reserve_pool:IDR',
                'name' => 'Central Treasury Insurance Reserve Pool',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'claimant:payable:default:IDR',
                'name' => 'Claimant Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:unearned_premium:IDR',
                'name' => 'Unearned Premium Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:earned_premium_revenue:IDR',
                'name' => 'Earned Premium Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:claim_expense:IDR',
                'name' => 'Insurance Claim Indemnity Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:claim_clearing:IDR',
                'name' => 'Insurance Claim Settlement Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function unearnedPremium(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'ins:unearned_premium:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Unearned Premium Liability',
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function earnedPremiumRevenue(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'ins:earned_premium_revenue:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Earned Premium Revenue',
                'asset_code' => 'IDR',
                'kind' => AccountKind::REVENUE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function claimExpense(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'ins:claim_expense:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Insurance Claim Indemnity Expense',
                'asset_code' => 'IDR',
                'kind' => AccountKind::EXPENSE->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function claimantPayable(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'claimant:payable:default:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Claimant Payable Default',
                'asset_code' => 'IDR',
                'kind' => AccountKind::LIABILITY->value,
                'allow_negative' => false,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }

    public static function claimClearing(): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => 'ins:claim_clearing:IDR'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Insurance Claim Settlement Clearing',
                'asset_code' => 'IDR',
                'kind' => AccountKind::CLEARING->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );
    }
}
