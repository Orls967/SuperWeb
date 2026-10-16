<?php

declare(strict_types=1);

namespace Modules\Venue\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Venue.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class VenueAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'artist:payable:default:IDR',
                'name' => 'Artist Payable Default',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'artist:payable:BC-ART-001:IDR',
                'name' => 'Artist Payable BC-ART-001',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'artist:payable:CON-ART-01:IDR',
                'name' => 'Artist Payable CON-ART-01',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'escrow:venue_bundle:IDR',
                'name' => 'Festival Staycation Escrow Bundle Holding',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'expense:venue_artist_fee:IDR',
                'name' => 'Festival Headline Artist Performance Fee Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hotel:resort:IDR',
                'name' => 'Resort Partner Staycation Payout Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'transport:shuttle:IDR',
                'name' => 'Festival Express Shuttle Transport Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:creator_held_royalty_escrow:IDR',
                'name' => 'Creator Held Royalty Escrow Hold',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:creator_payable:IDR',
                'name' => 'Creator & Performer Net Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:entertainment_tax_payable:IDR',
                'name' => 'Municipal Entertainment Tax Retained Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:merch_sales_receivable:IDR',
                'name' => 'Merch Cashless POS Collection Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:platform_merch_revenue:IDR',
                'name' => 'Merchandise Platform Consignment Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:platform_streaming_revenue:IDR',
                'name' => 'Pay-Per-View Live Stream Platform Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:resale_platform_fee_revenue:IDR',
                'name' => 'Secondary Ticket Marketplace Platform Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:resale_seller_payable:IDR',
                'name' => 'Verified Resale Ticket Seller Net Settlement Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:secondary_ticket_clearing:IDR',
                'name' => 'Secondary Market Anti-Scalping Ticket Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:streaming_receivable:IDR',
                'name' => 'PPV Live Stream Gateway Collection Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:ticket_clearing:IDR',
                'name' => 'Venue Box Office Gate Clearing',
                'kind' => AccountKind::CLEARING,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:ticket_revenue:IDR',
                'name' => 'Venue General Admission Ticket Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:vip_escrow:IDR',
                'name' => 'VIP Daybed & Cabana Advance Reservation Escrow',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'ven:vip_noshow_rev:IDR',
                'name' => 'VIP Daybed Reservation Forfeiture Penalty Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'ven:vip_unearned_rev:IDR',
                'name' => 'VIP Daybed Advance Booking Unearned Revenue Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
