<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Syariah\Domain\Models\SybCollateralAuction;
use Modules\Syariah\Domain\Models\SybRecoveryReceipt;
use Modules\Syariah\Domain\Models\SybWriteoffApproval;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

final class SyariahRecoveryWaterfallService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    /**
     * Mengajukan hapus buku pembiayaan macet (Kol 5) yang telah dicadangkan penuh (100% CKPN).
     */
    public function requestWriteoff(
        string $financingRef,
        string $customerRef,
        int $writtenOffPrincipalMinor,
        int $writtenOffMarginMinor,
        string $reason
    ): SybWriteoffApproval {
        $total = $writtenOffPrincipalMinor + $writtenOffMarginMinor;

        $certificatePayload = json_encode([
            'financing_ref' => $financingRef,
            'customer_ref' => $customerRef,
            'principal_minor' => $writtenOffPrincipalMinor,
            'margin_minor' => $writtenOffMarginMinor,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR);

        $hash = hash('sha256', $certificatePayload);

        return SybWriteoffApproval::create([
            'financing_ref' => $financingRef,
            'customer_ref' => $customerRef,
            'written_off_principal_minor' => $writtenOffPrincipalMinor,
            'written_off_margin_minor' => $writtenOffMarginMinor,
            'total_writeoff_minor' => $total,
            'status' => 'REQUESTED',
            'writeoff_reason' => $reason,
            'certificate_hash' => $hash,
        ]);
    }

    /**
     * Persetujuan bersama Dewan Pengawas Syariah dan Dewan Direksi untuk Hapus Buku.
     */
    public function approveWriteoff(
        string $financingRef,
        string $dpsApprovalRef,
        string $boardApprovalRef
    ): SybWriteoffApproval {
        $writeoff = SybWriteoffApproval::where('financing_ref', $financingRef)->firstOrFail();

        if ($writeoff->status !== 'REQUESTED') {
            throw new RuntimeException("Cannot approve writeoff in status {$writeoff->status}.");
        }

        $writeoff->update([
            'dps_approval_ref' => $dpsApprovalRef,
            'board_approval_ref' => $boardApprovalRef,
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);

        return $writeoff->fresh();
    }

    /**
     * Eksekusi hapus buku: debit cadangan CKPN, kredit piutang pembiayaan macet.
     */
    public function executeWriteoff(string $financingRef): SybWriteoffApproval
    {
        $writeoff = SybWriteoffApproval::where('financing_ref', $financingRef)->firstOrFail();

        if ($writeoff->status !== 'APPROVED') {
            throw new RuntimeException("Cannot execute writeoff in status {$writeoff->status}.");
        }

        return DB::transaction(function () use ($writeoff) {
            SyariahAccounts::ensureAccountsExist();

            // Posting ledger: Hapus buku dibebankan ke akun cadangan CKPN
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_NPF_PROVISION_RELEASE,
                reference: "WRITEOFF-{$writeoff->financing_ref}",
                description: "Eksekusi hapus buku pembiayaan macet {$writeoff->financing_ref}",
                entries: [
                    [
                        'account_code' => 'syb:npf_allowance_reserve:IDR',
                        'amount' => $writeoff->written_off_principal_minor,
                    ],
                    [
                        'account_code' => 'syb:micro_financing_receivable:IDR',
                        'amount' => -$writeoff->written_off_principal_minor,
                    ],
                ]
            );

            $writeoff->update([
                'status' => 'EXECUTED',
                'executed_at' => now(),
            ]);

            return $writeoff->fresh();
        });
    }

    /**
     * Mendaftarkan lelang agunan (marhun).
     */
    public function scheduleCollateralAuction(
        string $financingRef,
        string $collateralDescription,
        int $appraisedValueMinor,
        int $reservePriceMinor,
        Carbon $auctionDate
    ): SybCollateralAuction {
        $auctionRef = 'AUC-'.hash('sha256', $financingRef.microtime());
        $auctionRef = substr($auctionRef, 0, 32);

        return SybCollateralAuction::create([
            'auction_ref' => $auctionRef,
            'financing_ref' => $financingRef,
            'collateral_description' => $collateralDescription,
            'appraised_value_minor' => $appraisedValueMinor,
            'reserve_price_minor' => $reservePriceMinor,
            'winning_bid_minor' => 0,
            'buyer_ref' => null,
            'auction_date' => $auctionDate->toDateString(),
            'surplus_refunded_to_customer_minor' => 0,
            'status' => 'SCHEDULED',
        ]);
    }

    /**
     * Penyelesaian hasil lelang agunan (Fatwa DSN-MUI: Kelebihan dana lelang wajib dikembalikan).
     */
    public function settleAuction(
        string $auctionRef,
        int $winningBidMinor,
        string $buyerRef,
        int $outstandingDebtMinor
    ): SybCollateralAuction {
        $auction = SybCollateralAuction::where('auction_ref', $auctionRef)->firstOrFail();

        if ($auction->status !== 'SCHEDULED') {
            throw new RuntimeException('Auction is not in SCHEDULED status.');
        }

        if ($winningBidMinor < $auction->reserve_price_minor) {
            throw new RuntimeException('Winning bid cannot be below the reserve price.');
        }

        // Hitung kelebihan lelang di atas total hutang
        $surplusRefund = max(0, $winningBidMinor - $outstandingDebtMinor);

        $auction->update([
            'winning_bid_minor' => $winningBidMinor,
            'buyer_ref' => $buyerRef,
            'surplus_refunded_to_customer_minor' => $surplusRefund,
            'status' => 'COMPLETED',
        ]);

        return $auction->fresh();
    }

    /**
     * Memproses penerimaan pemulihan pembiayaan macet (recovery waterfall).
     */
    public function processRecovery(
        string $financingRef,
        string $source,
        int $amountMinor,
        int $outstandingPrincipalMinor
    ): SybRecoveryReceipt {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Recovery amount must be positive.');
        }

        $receiptRef = 'RCV-'.hash('sha256', $financingRef.microtime());
        $receiptRef = substr($receiptRef, 0, 32);

        // Alokasi waterfall: 1. Pokok tertunggak, 2. Sisa ke pendapatan margin, 3. Lebih ke kebajikan
        $principalRecovery = min($amountMinor, $outstandingPrincipalMinor);
        $remaining = $amountMinor - $principalRecovery;
        $marginRecovery = $remaining;

        return DB::transaction(function () use (
            $receiptRef,
            $financingRef,
            $source,
            $amountMinor,
            $principalRecovery,
            $marginRecovery
        ) {
            SyariahAccounts::ensureAccountsExist();

            $entries = [
                [
                    'account_code' => 'syb:wallet_float:IDR',
                    'amount' => $amountMinor,
                ],
                [
                    'account_code' => 'syb:npf_provision_expense:IDR',
                    'amount' => -$principalRecovery, // Pembalikan biaya CKPN
                ],
            ];

            if ($marginRecovery > 0) {
                $entries[] = [
                    'account_code' => 'syb:ujrah_income:IDR',
                    'amount' => -$marginRecovery,
                ];
            }

            $journalRef = 'syb:post:'.hash('sha256', "RECOVERY-{$receiptRef}");

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_NPF_PROVISION_RELEASE,
                reference: $receiptRef,
                description: "Penerimaan pemulihan pembiayaan macet {$financingRef} via {$source}",
                entries: $entries
            );

            return SybRecoveryReceipt::create([
                'receipt_ref' => $receiptRef,
                'financing_ref' => $financingRef,
                'recovery_source' => $source,
                'amount_minor' => $amountMinor,
                'principal_recovery_minor' => $principalRecovery,
                'margin_recovery_minor' => $marginRecovery,
                'allocated_to_charity_minor' => 0,
                'ledger_journal_ref' => $journalRef,
            ]);
        });
    }
}
