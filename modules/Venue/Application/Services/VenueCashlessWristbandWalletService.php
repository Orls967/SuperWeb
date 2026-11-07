<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\LedgerContract;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Models\VenueCashlessTransaction;
use Modules\Venue\Domain\Models\VenueCashlessWallet;
use Modules\Venue\Domain\Models\VenueWristband;
use Modules\Venue\Ledger\VenueTransactionTypes;
use RuntimeException;

final class VenueCashlessWristbandWalletService
{
    public function __construct(
        private readonly ?LedgerContract $ledger = null
    ) {}

    /**
     * Get or create cashless wallet for user/wristband.
     */
    public function getOrCreateWallet(User $user, ?VenueWristband $wristband = null): VenueCashlessWallet
    {
        $wallet = VenueCashlessWallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'wristband_id' => $wristband?->id,
                'balance_idr' => 0,
                'status' => 'active',
            ]
        );

        if ($wristband !== null && $wallet->wristband_id !== $wristband->id) {
            $wallet->wristband_id = $wristband->id;
            $wallet->save();
        }

        return $wallet;
    }

    /**
     * Top-up closed loop wallet with IDR minor units and post escrow journal.
     */
    public function topUp(VenueCashlessWallet $wallet, int $amountIdr, string $paymentRef): VenueCashlessTransaction
    {
        if ($amountIdr <= 0) {
            throw new RuntimeException('Nominal top up wajib bernilai positif.');
        }

        $idempotencyKey = "ven:cashless_topup:{$wallet->id}:{$paymentRef}";

        return DB::transaction(function () use ($wallet, $amountIdr, $paymentRef, $idempotencyKey) {
            $wallet->balance_idr += $amountIdr;
            $wallet->total_topped_up_idr += $amountIdr;
            $wallet->save();

            $tx = VenueCashlessTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'TOPUP',
                'amount_idr' => $amountIdr,
                'outlet_code' => 'TOPUP_BOOTH',
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['payment_ref' => $paymentRef],
            ]);

            if ($this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_VIP_TABLE_ESCROW->value,
                    description: "Cashless wristband top-up wallet #{$wallet->id}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:ticket_clearing:IDR', $amountIdr)
                        ->credit('ven:vip_escrow:IDR', $amountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CASHLESS_WALLET',
                    referenceId: (string) $wallet->id
                ));
            }

            return $tx;
        });
    }

    /**
     * Debit cashless wristband for bar beverage / outlet POS purchase.
     */
    public function debit(
        VenueCashlessWallet $wallet,
        int $amountIdr,
        string $outletCode,
        string $posReceiptNumber
    ): VenueCashlessTransaction {
        if ($amountIdr <= 0) {
            throw new RuntimeException('Nominal debit belanja wajib bernilai positif.');
        }

        if ($wallet->balance_idr < $amountIdr) {
            throw new RuntimeException("Saldo wristband tidak mencukupi (Saldo: Rp {$wallet->balance_idr}, Tagihan: Rp {$amountIdr}).");
        }

        $idempotencyKey = "ven:cashless_debit:{$wallet->id}:{$posReceiptNumber}";

        return DB::transaction(function () use ($wallet, $amountIdr, $outletCode, $posReceiptNumber, $idempotencyKey) {
            $wallet->balance_idr -= $amountIdr;
            $wallet->total_spent_idr += $amountIdr;
            $wallet->save();

            $tx = VenueCashlessTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'DEBIT',
                'amount_idr' => $amountIdr,
                'outlet_code' => $outletCode,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['receipt' => $posReceiptNumber],
            ]);

            if ($this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TABLE_SETTLE->value,
                    description: "Cashless tap purchase at {$outletCode} for wallet #{$wallet->id}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:vip_escrow:IDR', $amountIdr)
                        ->credit('ven:table_revenue:IDR', $amountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CASHLESS_WALLET',
                    referenceId: (string) $wallet->id
                ));
            }

            return $tx;
        });
    }

    /**
     * Refund remaining wallet balance post event.
     */
    public function refundRemainingBalance(VenueCashlessWallet $wallet): int
    {
        $remaining = (int) $wallet->balance_idr;
        if ($remaining <= 0) {
            return 0;
        }

        $idempotencyKey = "ven:cashless_refund:{$wallet->id}:{$wallet->updated_at?->timestamp}";

        return DB::transaction(function () use ($wallet, $remaining, $idempotencyKey) {
            $wallet->balance_idr = 0;
            $wallet->status = 'refunded';
            $wallet->save();

            VenueCashlessTransaction::create([
                'wallet_id' => $wallet->id,
                'transaction_type' => 'REFUND',
                'amount_idr' => $remaining,
                'outlet_code' => 'CS_REFUND_DESK',
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['refund_action' => 'POST_EVENT_SWEEP'],
            ]);

            if ($this->ledger !== null) {
                $this->ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TICKET_REFUND->value,
                    description: "Refund unspent cashless wristband balance wallet #{$wallet->id}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:vip_escrow:IDR', $remaining)
                        ->credit('ven:ticket_clearing:IDR', $remaining)
                        ->toEntries('IDR'),
                    referenceType: 'CASHLESS_WALLET',
                    referenceId: (string) $wallet->id
                ));
            }

            return $remaining;
        });
    }
}
