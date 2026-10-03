<?php

declare(strict_types=1);

namespace Modules\Payment\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerTransaction;
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

        return DB::transaction(function () use ($payable, $idempotencyKey) {
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

            $payableType = method_exists($payable, 'getMorphClass') ? $payable->getMorphClass() : get_class($payable);

            $txKey = 'tx_charge_'.$idempotencyKey;
            $dto = new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: $payable->payableDescription(),
                idempotencyKey: $txKey,
                entries: $entries,
                referenceType: $payableType,
                referenceId: method_exists($payable, 'getKey') ? $payable->getKey() : null,
                meta: [
                    'payer_id' => $payer->id,
                    'payable_type' => $payableType,
                    'amount' => $amount->amount->__toString(),
                ],
                createdBy: $payer->id,
                postedAt: now(),
            );

            $tx = $this->ledger->post($dto);

            try {
                $intent = PaymentIntent::create([
                    'uuid' => (string) Str::uuid(),
                    'payer_id' => $payer->id,
                    'payable_type' => $payableType,
                    'payable_id' => method_exists($payable, 'getKey') ? $payable->getKey() : null,
                    'amount' => $amount->amount->__toString(),
                    'currency' => $amount->assetCode,
                    'status' => PaymentIntentStatus::CAPTURED->value,
                    'capture_transaction_id' => $tx->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // Concurrent request with the same key won the race: return its intent.
                $existing = PaymentIntent::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }

                throw $e;
            }

            $payable->onPaymentCaptured($intent);
            DB::afterCommit(fn () => PaymentCaptured::dispatch($intent));

            return $intent;
        });
    }

    public function hold(Payable $payable, Money $amount, string $idempotencyKey, ?DateTimeInterface $expiresAt = null): PaymentIntent
    {
        $existing = PaymentIntent::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($payable, $amount, $idempotencyKey, $expiresAt) {
            $payer = User::findOrFail($payable->payerId());
            $payerWallet = $payer->walletAccount($amount->assetCode);
            $escrowCode = "escrow:payment:{$amount->assetCode}";

            $entries = [
                PostingEntryDTO::forAccount($payerWallet->id, $amount->assetCode, $amount->amount->negated()),
                PostingEntryDTO::forCode($escrowCode, $amount->assetCode, $amount->amount),
            ];

            $payableType = method_exists($payable, 'getMorphClass') ? $payable->getMorphClass() : get_class($payable);

            $txKey = 'tx_hold_'.$idempotencyKey;
            $dto = new PostingDTO(
                type: TransactionType::HOLD->value,
                description: 'Hold Escrow: '.$payable->payableDescription(),
                idempotencyKey: $txKey,
                entries: $entries,
                referenceType: $payableType,
                referenceId: method_exists($payable, 'getKey') ? $payable->getKey() : null,
                meta: [
                    'payer_id' => $payer->id,
                    'payable_type' => $payableType,
                    'amount' => $amount->amount->__toString(),
                ],
                createdBy: $payer->id,
                postedAt: now(),
            );

            $tx = $this->ledger->post($dto);

            try {
                $intent = PaymentIntent::create([
                    'uuid' => (string) Str::uuid(),
                    'payer_id' => $payer->id,
                    'payable_type' => $payableType,
                    'payable_id' => method_exists($payable, 'getKey') ? $payable->getKey() : null,
                    'amount' => $amount->amount->__toString(),
                    'currency' => $amount->assetCode,
                    'status' => PaymentIntentStatus::HELD->value,
                    'hold_transaction_id' => $tx->id,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at' => $expiresAt,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $existing = PaymentIntent::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }

                throw $e;
            }

            DB::afterCommit(fn () => PaymentHeld::dispatch($intent));

            return $intent;
        });
    }

    public function capture(PaymentIntent $intent, Money $finalAmount, ?string $idempotencyKey = null): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $finalAmount, $idempotencyKey) {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if (! $locked->isHeld()) {
                $key = $idempotencyKey ?? ('tx_cap_'.$locked->id);
                $existingTx = LedgerTransaction::where('idempotency_key', $key)->first();
                if ($locked->isCaptured() && $existingTx !== null && (int) $locked->capture_transaction_id === (int) $existingTx->id) {
                    return $locked;
                }

                throw InvalidPaymentStateException::fromTo($locked->status, PaymentIntentStatus::CAPTURED->value);
            }

            $holdAmount = $locked->money();
            if ($finalAmount->isGreaterThan($holdAmount)) {
                throw new InvalidArgumentException("Final amount {$finalAmount->format()} melebihi jumlah hold {$holdAmount->format()}.");
            }

            $escrowCode = "escrow:payment:{$holdAmount->assetCode}";
            $payer = $locked->payer;
            $payerWallet = $payer->walletAccount($holdAmount->assetCode);
            $remainder = $holdAmount->sub($finalAmount);

            // Deduct FULL hold amount from escrow
            $entries = [
                PostingEntryDTO::forCode($escrowCode, $holdAmount->assetCode, $holdAmount->amount->negated()),
            ];

            // Revenue splits for final amount
            $payable = $locked->payable;
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

            // Deterministic key: same intent + same caller key (or intent id) always yields the same posting.
            $key = $idempotencyKey ?? ('tx_cap_'.$locked->id);
            $dto = new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: 'Capture Escrow Payment Intent #'.$locked->id,
                idempotencyKey: $key,
                entries: $entries,
                referenceType: PaymentIntent::class,
                referenceId: $locked->id,
                meta: [
                    'intent_id' => $locked->id,
                    'final_amount' => $finalAmount->amount->__toString(),
                    'remainder' => $remainder->amount->__toString(),
                ],
                createdBy: $payer->id,
                postedAt: now(),
            );

            $tx = $this->ledger->post($dto);

            $locked->capture_transaction_id = $tx->id;
            $locked->amount = $finalAmount->amount->__toString();
            $locked->transitionTo(PaymentIntentStatus::CAPTURED);
            $locked->save();

            if ($payable instanceof Payable) {
                $payable->onPaymentCaptured($locked);
            }

            DB::afterCommit(fn () => PaymentCaptured::dispatch($locked));

            return $locked;
        });
    }

    public function release(PaymentIntent $intent, ?string $idempotencyKey = null): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $idempotencyKey) {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            if (! $locked->isHeld()) {
                if ($locked->isReleased() && $idempotencyKey !== null) {
                    $existingTx = LedgerTransaction::where('idempotency_key', $idempotencyKey)->first();
                    if ($existingTx !== null && (int) $locked->release_transaction_id === (int) $existingTx->id) {
                        return $locked;
                    }
                }

                throw InvalidPaymentStateException::fromTo($locked->status, PaymentIntentStatus::RELEASED->value);
            }

            $holdAmount = $locked->money();
            $escrowCode = "escrow:payment:{$holdAmount->assetCode}";
            $payer = $locked->payer;
            $payerWallet = $payer->walletAccount($holdAmount->assetCode);

            $entries = [
                PostingEntryDTO::forCode($escrowCode, $holdAmount->assetCode, $holdAmount->amount->negated()),
                PostingEntryDTO::forAccount($payerWallet->id, $holdAmount->assetCode, $holdAmount->amount),
            ];

            // Deterministic key: same intent always yields the same posting (safe to retry).
            $key = $idempotencyKey ?? ('tx_rel_'.$locked->id);
            $dto = new PostingDTO(
                type: TransactionType::RELEASE->value,
                description: 'Pelepasan Escrow Payment Intent #'.$locked->id,
                idempotencyKey: $key,
                entries: $entries,
                referenceType: PaymentIntent::class,
                referenceId: $locked->id,
                meta: ['intent_id' => $locked->id],
                createdBy: $payer->id,
                postedAt: now(),
            );

            $tx = $this->ledger->post($dto);

            $locked->release_transaction_id = $tx->id;
            $locked->transitionTo(PaymentIntentStatus::RELEASED);
            $locked->save();

            DB::afterCommit(fn () => PaymentReleased::dispatch($locked));

            return $locked;
        });
    }

    public function refund(PaymentIntent $intent, ?Money $refundAmount = null, string $reason = '', ?string $idempotencyKey = null): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $refundAmount, $reason, $idempotencyKey) {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->getKey());

            $capturedAmount = $locked->money();
            $alreadyRefunded = Money::of($locked->currency, $locked->refunded_amount ?: '0');
            $actualRefund = $refundAmount ?? $capturedAmount->sub($alreadyRefunded);

            // No amount left to refund: treat as idempotent replay, never as an error.
            if ($actualRefund->isZero()) {
                if ($alreadyRefunded->equals($capturedAmount)) {
                    return $locked;
                }

                throw new InvalidArgumentException('Nominal refund harus lebih besar dari 0.');
            }

            if ($actualRefund->isNegative()) {
                throw new InvalidArgumentException('Nominal refund tidak boleh negatif.');
            }

            // Deterministic key: intent + refund amount (no randomness) so retries deduplicate.
            $key = $idempotencyKey ?? ('tx_ref_'.$locked->id.'_'.$actualRefund->amount->__toString());

            // Replay guard must run before any state/limit checks so retries return the original result.
            if (LedgerTransaction::where('idempotency_key', $key)->exists()) {
                return $locked;
            }

            if (! $locked->isCaptured()) {
                throw InvalidPaymentStateException::fromTo($locked->status, PaymentIntentStatus::REFUNDED->value);
            }

            $totalAfter = $alreadyRefunded->add($actualRefund);
            if ($totalAfter->isGreaterThan($capturedAmount)) {
                throw new InvalidArgumentException(
                    'Total refund (Rp '.number_format((float) $totalAfter->amount->__toString(), 0, ',', '.').
                    ') melebihi jumlah yang di-capture (Rp '.number_format((float) $capturedAmount->amount->__toString(), 0, ',', '.').').'
                );
            }

            $payable = $locked->payable;
            $payer = $locked->payer;
            $payerWallet = $payer->walletAccount($actualRefund->assetCode);

            // Credit to payer wallet
            $entries = [
                PostingEntryDTO::forAccount($payerWallet->id, $actualRefund->assetCode, $actualRefund->amount),
            ];

            // Debit revenue proportionally to the refunded fraction of the captured amount.
            if ($payable instanceof Payable) {
                $splits = $payable->revenueSplits();
                if (! empty($splits)) {
                    $ratio = $capturedAmount->amount->isZero()
                        ? BigDecimal::one()
                        : $actualRefund->amount->dividedBy($capturedAmount->amount, 18, RoundingMode::HalfUp);
                    $allocated = BigDecimal::zero();
                    $codes = array_keys($splits);
                    $lastIndex = count($codes) - 1;

                    foreach ($codes as $i => $accountCode) {
                        $splitMoney = $splits[$accountCode];
                        if ($i < $lastIndex) {
                            $part = $splitMoney->amount->multipliedBy($ratio)->toScale(18, RoundingMode::HalfUp);
                            $allocated = $allocated->plus($part);
                        } else {
                            // Last split absorbs rounding so entries sum exactly to the refund.
                            $part = $actualRefund->amount->minus($allocated);
                        }

                        if (! $part->isZero()) {
                            $entries[] = PostingEntryDTO::forCode($accountCode, $splitMoney->assetCode, $part->negated());
                        }
                    }
                } else {
                    $entries[] = PostingEntryDTO::forCode("revenue:store:{$actualRefund->assetCode}", $actualRefund->assetCode, $actualRefund->amount->negated());
                }
            } else {
                $entries[] = PostingEntryDTO::forCode("revenue:store:{$actualRefund->assetCode}", $actualRefund->assetCode, $actualRefund->amount->negated());
            }

            $dto = new PostingDTO(
                type: TransactionType::REFUND->value,
                description: 'Pengembalian Dana (Refund) Intent #'.$locked->id.($reason ? ': '.$reason : ''),
                idempotencyKey: $key,
                entries: $entries,
                referenceType: PaymentIntent::class,
                referenceId: $locked->id,
                meta: [
                    'intent_id' => $locked->id,
                    'refund_amount' => $actualRefund->amount->__toString(),
                    'reason' => $reason,
                ],
                postedAt: now(),
            );

            $this->ledger->post($dto);

            $newRefunded = $alreadyRefunded->add($actualRefund);
            $locked->refunded_amount = $newRefunded->amount->__toString();

            $isFullRefund = $newRefunded->equals($capturedAmount);
            if ($isFullRefund) {
                $locked->transitionTo(PaymentIntentStatus::REFUNDED);
            }
            $locked->save();

            if ($isFullRefund && $payable instanceof Payable) {
                $payable->onPaymentRefunded($locked);
            }

            DB::afterCommit(fn () => PaymentRefunded::dispatch($locked, $actualRefund, $reason));

            return $locked;
        });
    }
}
