<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use DateTimeImmutable;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class VenueEconomyLedgerService
{
    public function __construct(
        private readonly Ledger $ledgerService
    ) {}

    /**
     * Memposting uang muka artis (Advance Payment) dengan pemotongan PPh.
     */
    public function postArtistAdvance(string $contractNumber, int $advanceIdr, int $whtIdr): void
    {
        if ($advanceIdr <= 0) {
            return;
        }

        $netAdvance = max(0, $advanceIdr - $whtIdr);
        $lines = Posting::lines()
            ->debit('ven:artist_advance:IDR', $advanceIdr)
            ->credit('ven:creator_payable:IDR', $netAdvance);

        if ($whtIdr > 0) {
            $lines->credit('ven:wht_payable:IDR', $whtIdr);
        }

        $idempotencyKey = 'IDEM:ART-ADV:'.$contractNumber;
        $posting = new PostingDTO(
            type: VenueTransactionTypes::VENUE_ARTIST_ADVANCE->value,
            description: "Pencairan Advance Artis Kontrak {$contractNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ARTIST_ADVANCE',
            referenceId: $contractNumber,
            postedAt: new DateTimeImmutable
        );

        $this->ledgerService->post($posting);
    }

    /**
     * Memposting penyelesaian akhir artis (Final Settlement) berbasis Gross Pay V5.
     */
    public function postArtistFinalSettlement(
        string $contractNumber,
        int $grossPayIdr,
        int $whtIdr,
        int $advancePaidIdr,
        int $netPayableIdr
    ): void {
        if ($grossPayIdr <= 0) {
            return;
        }

        $lines = Posting::lines()->debit('ven:artist_fee_expense:IDR', $grossPayIdr);

        if ($advancePaidIdr > 0) {
            $lines->credit('ven:artist_advance:IDR', $advancePaidIdr);
        }

        if ($whtIdr > 0) {
            $lines->credit('ven:wht_payable:IDR', $whtIdr);
        }

        if ($netPayableIdr > 0) {
            $lines->credit('ven:creator_payable:IDR', $netPayableIdr);
        }

        $idempotencyKey = 'IDEM:ART-SETTLE:'.$contractNumber;
        $posting = new PostingDTO(
            type: VenueTransactionTypes::VENUE_ARTIST_SETTLEMENT->value,
            description: "Penyelesaian Akhir Kontrak Artis {$contractNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'ARTIST_SETTLEMENT',
            referenceId: $contractNumber,
            postedAt: new DateTimeImmutable
        );

        $this->ledgerService->post($posting);
    }

    /**
     * Memposting penjualan paket bundling festival ke pendapatan diterima di muka (Unearned).
     */
    public function postFestivalBundleSale(string $orderNumber, int $bundlePriceIdr): void
    {
        if ($bundlePriceIdr <= 0) {
            return;
        }

        $idempotencyKey = 'IDEM:BNDL-SALE:'.$orderNumber;
        $lines = Posting::lines()
            ->debit('ven:ticket_clearing:IDR', $bundlePriceIdr)
            ->credit('ven:bundle_unearned:IDR', $bundlePriceIdr);

        $posting = new PostingDTO(
            type: VenueTransactionTypes::VENUE_BUNDLE_SALE->value,
            description: "Penjualan Bundle Festival Order {$orderNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'BUNDLE_SALE',
            referenceId: $orderNumber,
            postedAt: new DateTimeImmutable
        );

        $this->ledgerService->post($posting);
    }

    /**
     * Memposting amortisasi dan settlement komponen paket festival ke vendor mitra.
     */
    public function postFestivalBundleRedemption(
        string $orderNumber,
        string $componentName,
        int $allocatedAmountIdr,
        int $platformFeeIdr,
        int $vendorNetIdr
    ): void {
        if ($allocatedAmountIdr <= 0) {
            return;
        }

        $lines = Posting::lines()
            ->debit('ven:bundle_unearned:IDR', $allocatedAmountIdr)
            ->credit('ven:organizer_payable:IDR', $vendorNetIdr);

        if ($platformFeeIdr > 0) {
            $lines->credit('ven:platform_fee_revenue:IDR', $platformFeeIdr);
        }

        $idempotencyKey = 'IDEM:BNDL-RED:'.$orderNumber.':'.$componentName;
        $posting = new PostingDTO(
            type: VenueTransactionTypes::VENUE_BUNDLE_SETTLEMENT->value,
            description: "Settlement Komponen {$componentName} Order {$orderNumber}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'BUNDLE_REDEMPTION',
            referenceId: $orderNumber,
            postedAt: new DateTimeImmutable
        );

        $this->ledgerService->post($posting);
    }

    /**
     * Memposting akrual pendapatan sponsor berbasis pencapaian KPI milestone.
     */
    public function postSponsorMilestoneAccrual(string $dealCode, string $milestoneName, int $milestonePayoutIdr): void
    {
        if ($milestonePayoutIdr <= 0) {
            return;
        }

        $idempotencyKey = 'IDEM:SPON-ACCR:'.$dealCode.':'.$milestoneName;
        $lines = Posting::lines()
            ->debit('ven:sponsor_receivable:IDR', $milestonePayoutIdr)
            ->credit('ven:sponsorship_revenue:IDR', $milestonePayoutIdr);

        $posting = new PostingDTO(
            type: VenueTransactionTypes::VENUE_SPONSOR_ACCRUAL->value,
            description: "Akrual Milestone Sponsor {$dealCode} - {$milestoneName}",
            idempotencyKey: $idempotencyKey,
            entries: $lines->toEntries('IDR'),
            referenceType: 'SPONSOR_MILESTONE',
            referenceId: $dealCode,
            postedAt: new DateTimeImmutable
        );

        $this->ledgerService->post($posting);
    }
}
