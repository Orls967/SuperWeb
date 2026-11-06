<?php

declare(strict_types=1);

namespace Modules\Egy\Ledger;

use Modules\Banking\Contracts\LedgerAccount;
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
                'code' => 'egy:bess_energy_cost:IDR',
                'name' => 'BESS Energy Cost',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:bess_degradation_expense:IDR',
                'name' => 'BESS Degradation Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:bess_replacement_reserve:IDR',
                'name' => 'BESS Replacement Reserve',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:genset_fuel_expense:IDR',
                'name' => 'Genset Fuel Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
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
                'code' => 'egy:carbon_credit_cogs:IDR',
                'name' => 'Carbon Credit COGS',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:rec_revenue:IDR',
                'name' => 'Renewable Energy Certificate Revenue',
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
                'code' => 'egy:demand_response_expense:IDR',
                'name' => 'Demand Response Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:water_revenue:IDR',
                'name' => 'Water Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:thermal_revenue:IDR',
                'name' => 'District Cooling Thermal Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:gas_revenue:IDR',
                'name' => 'Gas Utility Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:tipping_fee_revenue:IDR',
                'name' => 'Waste Tipping Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ar:egy:government:IDR',
                'name' => 'AR Government Waste Tipping Fee',
                'kind' => AccountKind::ASSET,
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
            [
                'code' => 'egy:carbon_credit_sales_revenue:IDR',
                'name' => 'Carbon Credit Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:carbon_exchange_fee_revenue:IDR',
                'name' => 'Carbon Exchange Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'egy:cbam_tax_expense:IDR',
                'name' => 'CBAM Tax Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ap:egy:cbam_customs_authority:IDR',
                'name' => 'AP CBAM Customs Authority',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'egy:esg_marketplace_revenue:IDR',
                'name' => 'ESG Marketplace Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
        ];
    }

    public static function ensureAccount(string $code, string $name, AccountKind $kind, string $assetCode = 'IDR', bool $allowNegative = false): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'kind' => $kind,
                'asset_code' => $assetCode,
                'allow_negative' => $allowNegative,
            ]
        );
    }

    public static function customerReceivable(string $partyId): LedgerAccount
    {
        return self::getOrCreateCustomerAr($partyId);
    }

    public static function getOrCreateCustomerAr(string $partyId): LedgerAccount
    {
        $code = "ar:egy:customer:{$partyId}:IDR";

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "AR Customer {$partyId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
    }

    public static function getOrCreateGeneratorAp(string $partyId): LedgerAccount
    {
        $code = "ap:egy:generator:{$partyId}:IDR";

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "AP Generator {$partyId}",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
    }

    public static function getOrCreateEscoClientAr(string $partyId): LedgerAccount
    {
        $code = "ar:egy:esco_client:{$partyId}:IDR";

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "AR ESCO Client {$partyId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
    }

    public static function getOrCreateFuelSupplierAp(string $partyId): LedgerAccount
    {
        $code = "ap:egy:fuel_supplier:{$partyId}:IDR";

        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "AP Fuel Supplier {$partyId}",
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
    }
}
