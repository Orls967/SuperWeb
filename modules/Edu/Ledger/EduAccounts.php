<?php

declare(strict_types=1);

namespace Modules\Edu\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Edu.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EduAccounts
{
    public const string TUITION_RECEIVABLE = 'edu:tuition_receivable:IDR';

    public const string TUITION_REVENUE = 'edu:tuition_revenue:IDR';

    public const string CORPORATE_REVENUE = 'edu:corporate_revenue:IDR';

    public const string HEADHUNTER_RECEIVABLE = 'edu:headhunter_receivable:IDR';

    public const string HEADHUNTER_REVENUE = 'edu:headhunter_revenue:IDR';

    public const string ESCROW_HEADHUNTER_GUARANTEE = 'escrow:headhunter_guarantee:IDR';

    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'edu:headhunter_expense:IDR',
                'name' => 'Edu Headhunter Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'edu:headhunter_payable:IDR',
                'name' => 'Edu Headhunter Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::TUITION_RECEIVABLE,
                'name' => 'Edu Tuition Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::TUITION_REVENUE,
                'name' => 'Edu Tuition Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'edu:research_grant_expense:IDR',
                'name' => 'Edu Research Grant Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::CORPORATE_REVENUE,
                'name' => 'Edu Corporate Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'edu:escrow_liability:IDR',
                'name' => 'Edu Escrow Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::HEADHUNTER_RECEIVABLE,
                'name' => 'Edu Headhunter Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => self::HEADHUNTER_REVENUE,
                'name' => 'Edu Headhunter Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => self::ESCROW_HEADHUNTER_GUARANTEE,
                'name' => 'Escrow Headhunter Guarantee',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
