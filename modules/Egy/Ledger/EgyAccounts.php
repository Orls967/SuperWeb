<?php

declare(strict_types=1);

namespace Modules\Egy\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Egy.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class EgyAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'egy:bess_arbitrage_revenue:IDR',
                'name' => 'BESS Arbitrage Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:carbon_credit_inventory:IDR',
                'name' => 'Carbon Credit Inventory',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:carbon_credit_sales:IDR',
                'name' => 'Carbon Credit Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:cbam_receivable:IDR',
                'name' => 'CBAM Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:cbam_revenue:IDR',
                'name' => 'CBAM Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:esco_fee_receivable:IDR',
                'name' => 'ESCO Fee Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:esco_service_revenue:IDR',
                'name' => 'ESCO Service Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:green_incentive_contra_revenue:IDR',
                'name' => 'Green Incentive Contra Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:grid_electricity_revenue:IDR',
                'name' => 'Grid Electricity Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:power_purchase_expense:IDR',
                'name' => 'Power Purchase Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:producer_payable:IDR',
                'name' => 'Producer Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:utility_consolidated_revenue:IDR',
                'name' => 'Utility Consolidated Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:utility_revenue:IDR',
                'name' => 'Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:utility_receivable:IDR',
                'name' => 'Utility Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ar:ic:producer:egy:IDR',
                'name' => 'Intercompany AR Energy Producer to Egy',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:ic:egy:producer:IDR',
                'name' => 'Intercompany AP Egy to Energy Producer',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'revenue:energy:ppa:IDR',
                'name' => 'Revenue Energy PPA Generation',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'expense:energy:purchase:IDR',
                'name' => 'Expense Energy Purchase',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
