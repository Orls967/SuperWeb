<?php

declare(strict_types=1);

namespace Modules\Agri\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Agri.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class AgriAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'agri:farmer_loan_ar:IDR',
                'name' => 'Agri Farmer Microfinance Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'agri:escrow_disbursement:IDR',
                'name' => 'Agri Loan Escrow Disbursement Account',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'inventory:agri:raw_commodities:IDR',
                'name' => 'Agri Harvest Commodities Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'agri:farmer_advance_receivable:IDR',
                'name' => 'Agri Farmer Input Advance Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:agri:farmer_payable:IDR',
                'name' => 'Agri Farmer Harvest Net Payout Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
