<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for PaymentIntent model.
 */
class PaymentIntent extends Model
{
    protected $table = 'pay_payment_intents';
}
