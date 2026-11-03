<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Circular\Domain\Models\CirOrder;
use Modules\Circular\Domain\Models\CirSettlement;
use Modules\Circular\Ledger\CircularSubjectAccounts;
use Modules\Circular\Ledger\CircularTransactionTypes;
use Modules\Core\Contracts\ClockInterface;

final class SettleOrderAction
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    public function execute(
        CirOrder|int $order,
        string $idempotencyKey,
        int $feeBps = 250,
        bool $payoutImmediately = true
    ): CirSettlement {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency-Key wajib diisi untuk settlement order sirkular.',
            ]);
        }

        $orderModel = $order instanceof CirOrder ? $order : CirOrder::findOrFail($order);

        return DB::transaction(function () use ($orderModel, $key, $feeBps, $payoutImmediately): CirSettlement {
            $existing = CirSettlement::where('idempotency_key', $key)
                ->orWhere('order_id', $orderModel->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $locked = CirOrder::where('id', $orderModel->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'received' || $locked->accepted_grams === null) {
                throw ValidationException::withMessages([
                    'status' => 'Settlement penjual ditolak karena material belum diterima dan ditimbang di pabrik (status harus received).',
                ]);
            }

            $acceptedGrams = (int) $locked->accepted_grams;
            $grossAmountIdr = (int) round(($acceptedGrams * $locked->unit_price_per_kg_idr) / 1000, 0, PHP_ROUND_HALF_UP);
            $feeAmountIdr = (int) round(($grossAmountIdr * $feeBps) / 10000, 0, PHP_ROUND_HALF_UP);
            $netSellerAmountIdr = max(0, $grossAmountIdr - $feeAmountIdr);

            $sellerApCode = CircularSubjectAccounts::sellerPayable($locked->seller_party_id);
            $sellerWalletCode = CircularSubjectAccounts::userWallet($locked->seller_party_id);

            if ($grossAmountIdr > 0) {
                $lines = Posting::lines()->debit('escrow:payment:IDR', $grossAmountIdr);

                if ($netSellerAmountIdr > 0) {
                    $lines->credit($sellerApCode, $netSellerAmountIdr);
                }

                if ($feeAmountIdr > 0) {
                    $lines->credit('cir:fee_revenue:IDR', $feeAmountIdr);
                }

                $this->ledgerService->post(new PostingDTO(
                    type: CircularTransactionTypes::CIRCULAR_SELLER_SETTLEMENT->value,
                    description: "Settlement order sirkular {$locked->order_number} (fee {$feeBps} bps)",
                    idempotencyKey: "cir:settle:{$locked->id}:{$key}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'CIRCULAR_ORDER',
                    referenceId: (string) $locked->id,
                ));
            }

            if ($payoutImmediately && $netSellerAmountIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: CircularTransactionTypes::CIRCULAR_SELLER_PAYOUT->value,
                    description: "Payout penjual untuk order sirkular {$locked->order_number}",
                    idempotencyKey: "cir:payout:{$locked->id}:{$key}",
                    entries: Posting::lines()
                        ->debit($sellerApCode, $netSellerAmountIdr)
                        ->credit($sellerWalletCode, $netSellerAmountIdr)
                        ->toEntries('IDR'),
                    referenceType: 'CIRCULAR_ORDER',
                    referenceId: (string) $locked->id,
                ));
            }

            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();

            $settlement = CirSettlement::create([
                'order_id' => $locked->id,
                'seller_party_id' => $locked->seller_party_id,
                'gross_amount_idr' => $grossAmountIdr,
                'fee_bps' => $feeBps,
                'fee_amount_idr' => $feeAmountIdr,
                'net_seller_amount_idr' => $netSellerAmountIdr,
                'is_paid_out' => $payoutImmediately,
                'idempotency_key' => $key,
                'settled_at' => $now,
            ]);

            $locked->update([
                'status' => 'settled',
            ]);

            return $settlement;
        });
    }
}
