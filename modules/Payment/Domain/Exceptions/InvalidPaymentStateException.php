<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Exceptions;

use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use RuntimeException;

class InvalidPaymentStateException extends RuntimeException
{
    public static function fromTo(PaymentIntentStatus|string $from, PaymentIntentStatus|string $to): self
    {
        $fromStr = $from instanceof PaymentIntentStatus ? $from->value : $from;
        $toStr = $to instanceof PaymentIntentStatus ? $to->value : $to;

        return new self("Transisi status pembayaran tidak sah dari '{$fromStr}' ke '{$toStr}'.");
    }
}
