<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\SecondaryLedgerEntry;
use Modules\Venue\Domain\Models\TicketResale;

class VenueSecondaryLedgerService
{
    /**
     * Membukukan penerimaan dana pembeli ke akun Escrow pihak ketiga.
     */
    public function recordEscrowDeposit(TicketResale $resale): void
    {
        $code = 'ENT-DEP-'.strtoupper(substr(hash('sha256', (string) $resale->id.'DEPOSIT'.(string) $resale->resale_price_idr), 0, 16));

        SecondaryLedgerEntry::query()->create([
            'entry_code' => $code.'-DR',
            'resale_id' => $resale->id,
            'debit_account' => '1020-ESCROW-CLEARING',
            'credit_account' => '2010-ESCROW-LIABILITY',
            'amount_idr' => $resale->resale_price_idr,
            'description' => sprintf('Penerimaan dana escrow untuk listing tiket sekunder #%s', $resale->resale_code),
        ]);
    }

    /**
     * Membukukan distribusi waterfall penyelesaian (settlement) pasar sekunder:
     * - Seller Net Payout
     * - PBJT Pajak Hiburan 10%
     * - Platform Service Fee 8%
     * - Promoter / Artist Royalty 2%
     */
    public function recordSettlementWaterfall(
        TicketResale $resale,
        int $taxPbjtIdr,
        int $platformFeeIdr,
        int $promoterRoyaltyIdr,
        int $sellerPayoutIdr
    ): void {
        $baseHash = hash('sha256', (string) $resale->id.'SETTLE'.(string) $resale->resale_price_idr);

        // 1. Payout Penjual
        SecondaryLedgerEntry::query()->create([
            'entry_code' => 'ENT-SET-PAY-'.strtoupper(substr($baseHash, 0, 12)),
            'resale_id' => $resale->id,
            'debit_account' => '2010-ESCROW-LIABILITY',
            'credit_account' => '1010-CASH-DISBURSEMENT',
            'amount_idr' => $sellerPayoutIdr,
            'description' => sprintf('Pencairan bersih ke penjual tiket #%s', $resale->resale_code),
        ]);

        // 2. Pajak PBJT
        SecondaryLedgerEntry::query()->create([
            'entry_code' => 'ENT-SET-TAX-'.strtoupper(substr($baseHash, 12, 12)),
            'resale_id' => $resale->id,
            'debit_account' => '2010-ESCROW-LIABILITY',
            'credit_account' => '2050-PBJT-TAX-PAYABLE',
            'amount_idr' => $taxPbjtIdr,
            'description' => sprintf('Alokasi titipan PBJT 10%% tiket #%s', $resale->resale_code),
        ]);

        // 3. Biaya Platform
        SecondaryLedgerEntry::query()->create([
            'entry_code' => 'ENT-SET-FEE-'.strtoupper(substr($baseHash, 24, 12)),
            'resale_id' => $resale->id,
            'debit_account' => '2010-ESCROW-LIABILITY',
            'credit_account' => '4010-PLATFORM-REVENUE',
            'amount_idr' => $platformFeeIdr,
            'description' => sprintf('Pendapatan fee transaksi sekunder 8%% tiket #%s', $resale->resale_code),
        ]);

        // 4. Royalti Promotor
        SecondaryLedgerEntry::query()->create([
            'entry_code' => 'ENT-SET-ROY-'.strtoupper(substr($baseHash, 36, 12)),
            'resale_id' => $resale->id,
            'debit_account' => '2010-ESCROW-LIABILITY',
            'credit_account' => '2060-PROMOTER-ROYALTY-PAYABLE',
            'amount_idr' => $promoterRoyaltyIdr,
            'description' => sprintf('Royalti promotor/kreator 2%% tiket #%s', $resale->resale_code),
        ]);
    }
}
