<?php

declare(strict_types=1);

namespace Modules\B2b\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul B2B.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class B2bAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'escrow:b2b:orders:IDR',
                'name' => 'B2B Wholesale & Auction Escrow Held',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'clearing:b2b:deposit:IDR',
                'name' => 'B2B Buyer Deposit Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:b2b:seller_payable:IDR',
                'name' => 'B2B Vendor Escrow Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
