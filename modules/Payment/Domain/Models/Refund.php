<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Models;

use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    protected $table = 'pay_refunds';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'payment_intent_id',
        'amount',
        'currency',
        'reason',
        'status',
        'ledger_transaction_id',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function intent(): BelongsTo
    {
        return $this->paymentIntent();
    }

    protected function nominal(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) $this->amount,
            set: fn (mixed $value): array => ['amount' => (string) $value],
        );
    }

    protected function alasan(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->reason,
            set: fn (?string $value): array => ['reason' => $value],
        );
    }
}
