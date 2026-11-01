<?php

declare(strict_types=1);

namespace Modules\Mining\Ledger;

use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Mining.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3, §12F baris 1211-1219).
 */
final class MiningAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            // Standard MVP Vertical Slice Accounts (KONSEP §12F)
            [
                'code' => 'expense:min:royalty:IDR',
                'name' => 'Mining Statutory Royalty Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true, // Normal debit balance in single-sign convention
            ],
            [
                'code' => 'min:royalty_payable:IDR',
                'name' => 'Mining Statutory Royalty Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:penalty_income:IDR',
                'name' => 'Mining Contractor Violation Penalty Income',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'expense:min:fuel:IDR',
                'name' => 'Mining Haulage Fuel Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true, // Normal debit balance
            ],
            [
                'code' => 'min:sales_revenue:IDR',
                'name' => 'Mining Commodity Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],

            // Legacy & Extended Accounts
            [
                'code' => 'expense:mining_royalty:IDR',
                'name' => 'Mining Royalty Expense (Legacy)',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:base_metal_sales_revenue:IDR',
                'name' => 'Base Metal Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:byproduct_receivable:IDR',
                'name' => 'Smelter Byproduct Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:byproduct_sales_revenue:IDR',
                'name' => 'Smelter Byproduct Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:circular_economy_revenue:IDR',
                'name' => 'Slag & Tailings Circular Economy Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:contractor_receivable:IDR',
                'name' => 'Mining Contractor Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:demurrage_receivable:IDR',
                'name' => 'Barge & Vessel Demurrage Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:demurrage_revenue:IDR',
                'name' => 'Vessel Demurrage Claim Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:environmental_penalty_expense:IDR',
                'name' => 'Environmental Compliance Penalty Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:environmental_penalty_payable:IDR',
                'name' => 'Environmental Compliance Penalty Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:green_premium_revenue:IDR',
                'name' => 'Low-Carbon Green Metal Premium Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:intercompany_receivable:IDR',
                'name' => 'Mining Intercompany Renewable Power Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:marine_freight_expense:IDR',
                'name' => 'Marine Transshipment Freight Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:marine_vendor_payable:IDR',
                'name' => 'Barge & Tugboat Transshipment Vendor Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:metal_sales_revenue:IDR',
                'name' => 'Refined Nickel & Ferronickel Sales Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:reclamation_expense:IDR',
                'name' => 'Mine Site Reclamation Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:reclamation_provision_liability:IDR',
                'name' => 'Mine Site Post-Closure Reclamation Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:renewable_power_revenue:IDR',
                'name' => 'Solar Farm Captive Power Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:safety_penalty_revenue:IDR',
                'name' => 'Contractor HSE Violation Penalty Revenue (Legacy)',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'min:trade_receivable:IDR',
                'name' => 'Metals Trading & Offtake Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:water_fee_payable:IDR',
                'name' => 'Industrial Acid Water Neutralization Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'min:water_treatment_expense:IDR',
                'name' => 'Acid Mine Drainage Water Treatment Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }

    public static function royaltyExpense(): string
    {
        return 'expense:min:royalty:IDR';
    }

    public static function royaltyPayable(): string
    {
        return 'min:royalty_payable:IDR';
    }

    public static function penaltyIncome(): string
    {
        return 'min:penalty_income:IDR';
    }

    public static function contractorReceivable(string $partyId): string
    {
        $code = "ar:min:contractor:{$partyId}:IDR";
        LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => "Contractor Fuel Penalty Receivable {$partyId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        return $code;
    }

    public static function fuelExpense(): string
    {
        return 'expense:min:fuel:IDR';
    }

    public static function salesRevenue(): string
    {
        return 'min:sales_revenue:IDR';
    }
}
