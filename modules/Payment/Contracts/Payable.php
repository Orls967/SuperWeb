<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

interface Payable
{
    public function payableAmount(): Money;

    public function payableDescription(): string;

    public function payerId(): int;

    /**
     * @return array<string, Money> [accountCode => Money]
     */
    public function revenueSplits(): array;

    public function onPaymentCaptured(PaymentIntent $intent): void;

    public function onPaymentRefunded(PaymentIntent $intent): void;
}
