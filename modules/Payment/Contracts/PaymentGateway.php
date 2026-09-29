<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use DateTimeInterface;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

interface PaymentGateway
{
    public function charge(Payable $payable, string $idempotencyKey): PaymentIntent;

    public function hold(Payable $payable, Money $amount, string $idempotencyKey, ?DateTimeInterface $expiresAt = null): PaymentIntent;

    public function capture(PaymentIntent $intent, Money $finalAmount, ?string $idempotencyKey = null): PaymentIntent;

    public function release(PaymentIntent $intent, ?string $idempotencyKey = null): PaymentIntent;

    public function refund(PaymentIntent $intent, ?Money $refundAmount = null, string $reason = '', ?string $idempotencyKey = null): PaymentIntent;
}
