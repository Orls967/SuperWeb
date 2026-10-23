<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Domain\Models\Refund;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'payment_intent_id' => 1,
            'amount' => '100000',
            'currency' => 'IDR',
            'reason' => 'Customer cancellation',
            'status' => 'succeeded',
            'ledger_transaction_id' => null,
        ];
    }
}
