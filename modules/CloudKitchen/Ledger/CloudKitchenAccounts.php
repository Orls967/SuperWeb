<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul CloudKitchen.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class CloudKitchenAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'hcm:meals_deduction:IDR',
                'name' => 'HCM Meals Deduction Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'resto:catering_revenue:IDR',
                'name' => 'Resto Catering Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ckt:catering_revenue:IDR',
                'name' => 'Cloud Kitchen Catering Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'expense:resto:cogs:IDR',
                'name' => 'Resto and Cloud Kitchen COGS Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'inventory:resto:central:IDR',
                'name' => 'Resto Central Kitchen Inventory Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
