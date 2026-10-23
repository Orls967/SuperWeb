<?php

declare(strict_types=1);

namespace Modules\Hcm\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Hcm.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class HcmAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'hcm:bounty_payout:IDR',
                'name' => 'HCM Bounty Payout Wallet',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hcm:gig_cost_expense:IDR',
                'name' => 'Internal Gig Cost Center Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:hcm:salaries:IDR',
                'name' => 'HCM Employee Salaries and Allowances Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'tax:hcm:pph21_payable:IDR',
                'name' => 'HCM PPh 21 Employee Tax Withholding Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:hcm:bpjs_payable:IDR',
                'name' => 'HCM BPJS Ketenagakerjaan and Kesehatan Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:hcm:payroll_payable:IDR',
                'name' => 'HCM Net Employee Payroll Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'mfg:labor_wip:IDR',
                'name' => 'Manufacturing Direct Labor Work in Progress',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:hcm:labor_absorbed:IDR',
                'name' => 'HCM Direct Labor Absorbed into Manufacturing',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hotel:gig_expense:IDR',
                'name' => 'Hotel Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'venue:gig_expense:IDR',
                'name' => 'Venue Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'mining:gig_expense:IDR',
                'name' => 'Mining Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'logistics:gig_expense:IDR',
                'name' => 'Logistics Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hospital:gig_expense:IDR',
                'name' => 'Hospital Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'resto:gig_expense:IDR',
                'name' => 'Resto Gig Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'payroll:worker_payable:20:IDR',
                'name' => 'Worker 20 Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
