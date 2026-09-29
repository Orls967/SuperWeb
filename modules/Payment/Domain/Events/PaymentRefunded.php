<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\ValueObjects\Money;

class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentIntent $intent,
        public Money $refundAmount,
        public string $reason = '',
    ) {}
}
