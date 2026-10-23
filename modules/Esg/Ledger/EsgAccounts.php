<?php

declare(strict_types=1);

namespace Modules\Esg\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul ESG.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EsgAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'inventory:esg:carbon_credit:IDR',
                'name' => 'ESG Carbon Credit Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:esg:carbon_offset:IDR',
                'name' => 'ESG Carbon Offset Neutralization Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:esg:carbon_registry:IDR',
                'name' => 'ESG Carbon Registry Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'esg:carbon_credit_inventory:IDR',
                'name' => 'ESG Carbon Credit Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'esg:carbon_offset_expense:IDR',
                'name' => 'ESG Carbon Offset Neutralization Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
