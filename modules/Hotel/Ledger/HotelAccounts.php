<?php

declare(strict_types=1);

namespace Modules\Hotel\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Hotel.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class HotelAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'air:flight_partner_payable:IDR',
                'name' => 'Flight Partner Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'escrow:destination_package:IDR',
                'name' => 'Destination Package Escrow Holding',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:htl_timeshare_payout:IDR',
                'name' => 'Timeshare Investor Payout Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:banquet_production_cost:IDR',
                'name' => 'Banquet Production Cost Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:banquet_vendor_payable:IDR',
                'name' => 'Banquet Vendor Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:franchise_fee_revenue:IDR',
                'name' => 'Hotel Franchise Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'htl:franchise_receivable:IDR',
                'name' => 'Hotel Franchise Fee Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:investor_token_payable:IDR',
                'name' => 'Timeshare Token Payout Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:loyalty_points_liability:IDR',
                'name' => 'Hotel Loyalty Points Outstanding Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:loyalty_program_expense:IDR',
                'name' => 'Hotel Loyalty Program Marketing Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:loyalty_redemption_settlement:IDR',
                'name' => 'Hotel Loyalty Redemption Settlement Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:ota_penalty_receivable:IDR',
                'name' => 'OTA Parity Penalty Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:parity_penalty_revenue:IDR',
                'name' => 'OTA Rate Parity Violation Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'htl:room_revenue:IDR',
                'name' => 'Hotel Room Night Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'htl:royalty_revenue:IDR',
                'name' => 'Hotel Brand Royalty Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'htl:syndication_yield_clearing:IDR',
                'name' => 'Hotel Syndication Yield Distribution Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:travel_escrow_deposit:IDR',
                'name' => 'Travel Escrow Deposit Holding',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:travel_unearned_escrow:IDR',
                'name' => 'Travel Package Unearned Escrow Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:wedding_receivable:IDR',
                'name' => 'MICE Wedding & Event Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:wedding_revenue:IDR',
                'name' => 'MICE Wedding & Event Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'investor:dividend:default:IDR',
                'name' => 'Investor Dividend Payout Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'investor:dividend:INV-001:IDR',
                'name' => 'Investor Dividend Payout INV-001',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'investor:dividend:INV-002:IDR',
                'name' => 'Investor Dividend Payout INV-002',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'investor:dividend:00000000-0000-0000-0000-000000000001:IDR',
                'name' => 'Investor Dividend Payout 001',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'investor:dividend:00000000-0000-0000-0000-000000000002:IDR',
                'name' => 'Investor Dividend Payout 002',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:event_vendor_payable:IDR',
                'name' => 'Banquet Event Vendor Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'vendor:hotel:IDR',
                'name' => 'Hotel Partner Vendor Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'vendor:festival:IDR',
                'name' => 'Festival Vendor Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'vendor:transport:IDR',
                'name' => 'Transport Vendor Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
