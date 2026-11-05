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
    public const string CEDED_PREMIUM_EXPENSE = 'reins:ceded_premium_expense:IDR';

    public const string COMMISSION_INCOME = 'reins:commission_income:IDR';

    public const string REINSTATEMENT_EXPENSE = 'reins:reinstatement_premium_expense:IDR';

    public const string CLAIM_EXPENSE = 'ins:claim_expense:IDR';

    public const string CLAIM_CLEARING = 'ins:claim_clearing:IDR';

    public const string UNEARNED_PREMIUM = 'ins:unearned_premium:IDR';

    public const string PREMIUM_RECEIVABLE = 'ar:ins:premium:IDR';

    public const string COMMUTATION_LOSS = 'reins:commutation_loss:IDR';

    public const string OWN_FUNDS = 'ins:own_funds:IDR';

    public const string RESERVE_POOL = 'ins:reserve_pool:IDR';

    public const string CATBOND_COUPON_EXPENSE = 'ins:catbond_coupon_expense:IDR';

    public const string CATBOND_RECOVERABLE = 'ins:catbond_recoverable:IDR';

    public const string BANK_CLEARING = 'clearing:external:IDR';

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
                'allow_negative' => true,
            ],
            [
                'code' => 'ins:earned_premium_revenue:IDR',
                'name' => 'Earned Premium Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ins:claim_expense:IDR',
                'name' => 'Insurance Claim Indemnity Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ins:claim_clearing:IDR',
                'name' => 'Insurance Claim Settlement Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'reins:ceded_premium_expense:IDR',
                'name' => 'Reinsurance Ceded Premium Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'reins:claims_recoverable:IDR',
                'name' => 'Reinsurance Claims Recoverable Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'reins:commission_income:IDR',
                'name' => 'Reinsurance Ceding Commission Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'reins:reinstatement_premium_expense:IDR',
                'name' => 'Reinsurance Reinstatement Premium Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'reins:commutation_loss:IDR',
                'name' => 'Reinsurance Commutation Discount Loss',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ins:own_funds:IDR',
                'name' => 'Insurer Own Funds (Available Capital)',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ins:catbond_coupon_expense:IDR',
                'name' => 'Cat Bond Coupon Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ins:catbond_recoverable:IDR',
                'name' => 'Cat Bond Trigger Recoverable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:ins:premium:IDR',
                'name' => 'Accounts Receivable Insurance Premium',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    /** Utang ke reinsurer (kredit-normal; boleh negatif saat settlement mendahului akrual). */
    public static function reinsurerPayable(string $partyId): string
    {
        return self::ensureSubject("reins:payable:{$partyId}:IDR", "Reinsurance Payable {$partyId}", AccountKind::AP);
    }

    /** Piutang recoverable dari reinsurer (debit-normal). */
    public static function reinsurerRecoverable(string $partyId): string
    {
        return self::ensureSubject("reins:recoverable:{$partyId}:IDR", "Reinsurance Recoverable {$partyId}", AccountKind::ASSET);
    }

    private static function ensureSubject(string $code, string $name, AccountKind $kind): string
    {
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'asset_code' => 'IDR',
                'kind' => $kind->value,
                'allow_negative' => true,
                'cached_balance' => '0',
                'is_frozen' => false,
            ]
        );

        return $code;
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
                'allow_negative' => true,
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
                'allow_negative' => true,
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
