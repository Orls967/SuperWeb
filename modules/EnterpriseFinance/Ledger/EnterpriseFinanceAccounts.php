<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul EnterpriseFinance.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EnterpriseFinanceAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'ef:holding_dividend_income:IDR',
                'name' => 'Holding Dividend Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ef:holding_dividend_receivable:IDR',
                'name' => 'Holding Dividend Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_facility_commitment:IDR',
                'name' => 'Syndicated Facility Commitment',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_loan_liability:IDR',
                'name' => 'Syndicated Loan Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:syndicated_interest_expense:IDR',
                'name' => 'Syndicated Interest Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ef:operating_budget_clearing:IDR',
                'name' => 'Operating Budget Clearing',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:cash_disbursement_clearing:IDR',
                'name' => 'Cash Disbursement Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:tax_payable_ppn:IDR',
                'name' => 'PPN Output Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ef:tax_prepaid_clearing:IDR',
                'name' => 'Prepaid Tax Input Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
