<?php

declare(strict_types=1);

namespace Modules\Payment\Application\Services;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Events\PaymentCaptured;
use Modules\Payment\Domain\Events\PaymentHeld;
use Modules\Payment\Domain\Events\PaymentRefunded;
use Modules\Payment\Domain\Events\PaymentReleased;
use Modules\Payment\Domain\Exceptions\InvalidPaymentStateException;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

class PaymentGatewayService implements PaymentGateway
{
    public function __construct(
        protected Ledger $ledger,
    ) {}

    public function charge(Payable $payable, string $idempotencyKey): PaymentIntent
    {
        $existing = PaymentIntent::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = $payable->payableAmount();
        $payer = User::findOrFail($payable->payerId());
        $payerWallet = $payer->walletAccount($amount->assetCode);

        // Build double-entry postings
        $entries = [
            PostingEntryDTO::forAccount($payerWallet->id, $amount->assetCode, $amount->amount->negated()),
        ];

        $splits = $payable->revenueSplits();
        if (empty($splits)) {
            // Default split to revenue store / service
            $defaultRevCode = $amount->assetCode === 'IDR' ? 'revenue:store:IDR' : "revenue:store:{$amount->assetCode}";
            $entries[] = PostingEntryDTO::forCode($defaultRevCode, $amount->assetCode, $amount->amount);
        } else {
            foreach ($splits as $accountCode => $splitMoney) {
                if ($splitMoney->isPositive()) {
                    $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $splitMoney->amount);
                }
            }
        }

        $txKey = 'tx_charge_'.$idempotencyKey;
        $dto = new PostingDTO(
            type: TransactionType::PAYMENT->value,
            description: $payable->payableDescription(),
            idempotencyKey: $txKey,
            entries: $entries,
            referenceType: get_class($payable),
            referenceId: method_exists($payable, 'getKey') ? $payable->getKey() : null,
            meta: [
                'payer_id' => $payer->id,
                'payable_type' => get_class($payable),
                'amount' => $amount->amount->__toString(),
            ],
            createdBy: $payer->id,
            postedAt: now(),
        );

        $tx = $this->ledger->post($dto);

        $intent = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $payer->id,
            'payable_type' => get_class($payable),
            'payable_id' => method_exists($payable, 'getKey') ? $payable->getKey() : null,
            'amount' => $amount->amount->__toString(),
            'currency' => $amount->assetCode,
            'status' => PaymentIntentStatus::CAPTURED->value,
            'capture_transaction_id' => $tx->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $payable->onPaymentCaptured($intent);
        PaymentCaptured::dispatch($intent);

        return $intent;
    }

    public function hold(Payable $payable, Money $amount, string $idempotencyKey, ?DateTimeInterface $expiresAt = null): PaymentIntent
    {
        $existing = PaymentIntent::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $payer = User::findOrFail($payable->payerId());
        $payerWallet = $payer->walletAccount($amount->assetCode);
        $escrowCode = "escrow:payment:{$amount->assetCode}";

        $entries = [
            PostingEntryDTO::forAccount($payerWallet->id, $amount->assetCode, $amount->amount->negated()),
            PostingEntryDTO::forCode($escrowCode, $amount->assetCode, $amount->amount),
        ];

        $txKey = 'tx_hold_'.$idempotencyKey;
        $dto = new PostingDTO(
            type: TransactionType::HOLD->value,
            description: 'Hold Escrow: '.$payable->payableDescription(),
            idempotencyKey: $txKey,
            entries: $entries,
            referenceType: get_class($payable),
            referenceId: method_exists($payable, 'getKey') ? $payable->getKey() : null,
            meta: [
                'payer_id' => $payer->id,
                'payable_type' => get_class($payable),
                'amount' => $amount->amount->__toString(),
            ],
            createdBy: $payer->id,
            postedAt: now(),
        );

        $tx = $this->ledger->post($dto);

        $intent = PaymentIntent::create([
            'uuid' => (string) Str::uuid(),
            'payer_id' => $payer->id,
            'payable_type' => get_class($payable),
            'payable_id' => method_exists($payable, 'getKey') ? $payable->getKey() : null,
            'amount' => $amount->amount->__toString(),
            'currency' => $amount->assetCode,
            'status' => PaymentIntentStatus::HELD->value,
            'hold_transaction_id' => $tx->id,
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt,
        ]);

        PaymentHeld::dispatch($intent);

        return $intent;
    }

    public function capture(PaymentIntent $intent, Money $finalAmount, ?string $idempotencyKey = null): PaymentIntent
    {
        if (! $intent->isHeld()) {
            throw InvalidPaymentStateException::fromTo($intent->status, PaymentIntentStatus::CAPTURED->value);
        }

        $holdAmount = $intent->money();
        if ($finalAmount->isGreaterThan($holdAmount)) {
            throw new InvalidArgumentException("Final amount {$finalAmount->format()} melebihi jumlah hold {$holdAmount->format()}.");
        }

        $escrowCode = "escrow:payment:{$holdAmount->assetCode}";
        $payer = $intent->payer;
        $payerWallet = $payer->walletAccount($holdAmount->assetCode);
        $remainder = $holdAmount->sub($finalAmount);

        // Deduct FULL hold amount from escrow
        $entries = [
            PostingEntryDTO::forCode($escrowCode, $holdAmount->assetCode, $holdAmount->amount->negated()),
        ];

        // Revenue splits for final amount
        $payable = $intent->payable;
        if ($payable instanceof Payable) {
            $splits = $payable->revenueSplits();
            if (! empty($splits)) {
                foreach ($splits as $accountCode => $splitMoney) {
                    if ($splitMoney->isPositive()) {
                        $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $splitMoney->amount);
                    }
                }
            } else {
                $entries[] = PostingEntryDTO::forCode("revenue:store:{$holdAmount->assetCode}", $holdAmount->assetCode, $finalAmount->amount);
            }
        } else {
            $entries[] = PostingEntryDTO::forCode("revenue:store:{$holdAmount->assetCode}", $holdAmount->assetCode, $finalAmount->amount);
        }

        // Return remainder back to payer wallet in the exact same transaction!
        if ($remainder->isPositive()) {
            $entries[] = PostingEntryDTO::forAccount($payerWallet->id, $remainder->assetCode, $remainder->amount);
        }

        $key = $idempotencyKey ?? ('tx_cap_'.$intent->id.'_'.Str::random(12));
        $dto = new PostingDTO(
            type: TransactionType::PAYMENT->value,
            description: 'Capture Escrow Payment Intent #'.$intent->id,
            idempotencyKey: $key,
            entries: $entries,
            referenceType: PaymentIntent::class,
            referenceId: $intent->id,
            meta: [
                'intent_id' => $intent->id,
                'final_amount' => $finalAmount->amount->__toString(),
                'remainder' => $remainder->amount->__toString(),
            ],
            createdBy: $payer->id,
            postedAt: now(),
        );

        $tx = $this->ledger->post($dto);

        $intent->capture_transaction_id = $tx->id;
        $intent->amount = $finalAmount->amount->__toString();
        $intent->transitionTo(PaymentIntentStatus::CAPTURED);

        if ($payable instanceof Payable) {
            $payable->onPaymentCaptured($intent);
        }

        PaymentCaptured::dispatch($intent);

        return $intent;
    }

    public function release(PaymentIntent $intent, ?string $idempotencyKey = null): PaymentIntent
    {
        if (! $intent->isHeld()) {
            throw InvalidPaymentStateException::fromTo($intent->status, PaymentIntentStatus::RELEASED->value);
        }

        $holdAmount = $intent->money();
        $escrowCode = "escrow:payment:{$holdAmount->assetCode}";
        $payer = $intent->payer;
        $payerWallet = $payer->walletAccount($holdAmount->assetCode);

        $entries = [
            PostingEntryDTO::forCode($escrowCode, $holdAmount->assetCode, $holdAmount->amount->negated()),
            PostingEntryDTO::forAccount($payerWallet->id, $holdAmount->assetCode, $holdAmount->amount),
        ];

        $key = $idempotencyKey ?? ('tx_rel_'.$intent->id.'_'.Str::random(12));
        $dto = new PostingDTO(
            type: TransactionType::RELEASE->value,
            description: 'Pelepasan Escrow Payment Intent #'.$intent->id,
            idempotencyKey: $key,
            entries: $entries,
            referenceType: PaymentIntent::class,
            referenceId: $intent->id,
            meta: ['intent_id' => $intent->id],
            createdBy: $payer->id,
            postedAt: now(),
        );

        $tx = $this->ledger->post($dto);

        $intent->release_transaction_id = $tx->id;
        $intent->transitionTo(PaymentIntentStatus::RELEASED);

        PaymentReleased::dispatch($intent);

        return $intent;
    }

    public function refund(PaymentIntent $intent, ?Money $refundAmount = null, string $reason = '', ?string $idempotencyKey = null): PaymentIntent
    {
        if (! $intent->isCaptured()) {
            throw InvalidPaymentStateException::fromTo($intent->status, PaymentIntentStatus::REFUNDED->value);
        }

        $capturedAmount = $intent->money();
        $actualRefund = $refundAmount ?? $capturedAmount;

        $payer = $intent->payer;
        $payerWallet = $payer->walletAccount($actualRefund->assetCode);

        // Credit to payer wallet
        $entries = [
            PostingEntryDTO::forAccount($payerWallet->id, $actualRefund->assetCode, $actualRefund->amount),
        ];

        // Debit from revenue splits
        $payable = $intent->payable;
        if ($payable instanceof Payable) {
            $splits = $payable->revenueSplits();
            if (! empty($splits)) {
                foreach ($splits as $accountCode => $splitMoney) {
                    if ($splitMoney->isPositive()) {
                        // Reverse revenue
                        $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $splitMoney->amount->negated());
                    }
                }
            } else {
                $entries[] = PostingEntryDTO::forCode("revenue:store:{$actualRefund->assetCode}", $actualRefund->assetCode, $actualRefund->amount->negated());
            }
        } else {
            $entries[] = PostingEntryDTO::forCode("revenue:store:{$actualRefund->assetCode}", $actualRefund->assetCode, $actualRefund->amount->negated());
        }

        $key = $idempotencyKey ?? ('tx_ref_'.$intent->id.'_'.Str::random(12));
        $dto = new PostingDTO(
            type: TransactionType::REFUND->value,
            description: 'Pengembalian Dana (Refund) Intent #'.$intent->id.($reason ? ': '.$reason : ''),
            idempotencyKey: $key,
            entries: $entries,
            referenceType: PaymentIntent::class,
            referenceId: $intent->id,
            meta: [
                'intent_id' => $intent->id,
                'refund_amount' => $actualRefund->amount->__toString(),
                'reason' => $reason,
            ],
            postedAt: now(),
        );

        $tx = $this->ledger->post($dto);

        if ($actualRefund->equals($capturedAmount)) {
            $intent->transitionTo(PaymentIntentStatus::REFUNDED);
        }

        if ($payable instanceof Payable) {
            $payable->onPaymentRefunded($intent);
        }

        PaymentRefunded::dispatch($intent, $actualRefund, $reason);

        return $intent;
    }
}
