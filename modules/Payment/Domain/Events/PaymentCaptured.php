<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Domain\Models\PaymentIntent;

class PaymentCaptured
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentIntent $intent,
    ) {}
}
