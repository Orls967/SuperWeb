<?php

declare(strict_types=1);

namespace Modules\Ret\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Ret.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class RetAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'ret:bill_payment_fee_revenue:IDR',
                'name' => 'PPOB & Bill Payment Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ret:bill_payment_receivable:IDR',
                'name' => 'PPOB User Collection Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:biller_payable:IDR',
                'name' => 'Utility & Telco Aggregator Biller Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:bundle_receivable:IDR',
                'name' => 'Cross-Border Bundle Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:cashback_expense:IDR',
                'name' => 'SuperApp Promotional Cashback Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:cashback_liability:IDR',
                'name' => 'Customer Cashback Wallet Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:customer_credit_liability:IDR',
                'name' => 'Quick-Commerce Customer Credit Note Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:escrow_receivable:IDR',
                'name' => 'Omnichannel Payment Gateway Escrow Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:marketplace_commission_revenue:IDR',
                'name' => 'Marketplace Commission Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ret:packaging_deposit_cash:IDR',
                'name' => 'Eco-Packaging Reusable Container Deposit Asset',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:packaging_deposit_liability:IDR',
                'name' => 'Customer Eco-Packaging Returnable Deposit Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:internal_settlement_HOTEL:IDR',
                'name' => 'Ret Internal Settlement HOTEL',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:internal_settlement_MEDIA:IDR',
                'name' => 'Ret Internal Settlement MEDIA',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:internal_settlement_TLX:IDR',
                'name' => 'Ret Internal Settlement TLX',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:seller_payable:IDR',
                'name' => 'Omnichannel Merchant Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ret:sla_compensation_expense:IDR',
                'name' => 'Delivery SLA Delay Compensation Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
