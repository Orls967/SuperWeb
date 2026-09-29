<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Exceptions\InvalidPaymentStateException;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class PaymentIntent extends Model
{
    use HasUuid;

    protected $table = 'pay_payment_intents';

    protected $fillable = [
        'uuid',
        'payer_id',
        'payable_type',
        'payable_id',
        'amount',
        'currency',
        'status',
        'hold_transaction_id',
        'capture_transaction_id',
        'release_transaction_id',
        'idempotency_key',
        'expires_at',
        'failure_reason',
    ];

    protected $casts = [
        'status' => PaymentIntentStatus::class,
        'amount' => 'string',
        'expires_at' => 'datetime',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function holdTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'hold_transaction_id');
    }

    public function captureTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'capture_transaction_id');
    }

    public function releaseTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'release_transaction_id');
    }

    public function money(): Money
    {
        return Money::of($this->currency, $this->amount ?: '0');
    }

    public function getStatusEnum(): PaymentIntentStatus
    {
        return $this->status instanceof PaymentIntentStatus
            ? $this->status
            : PaymentIntentStatus::from($this->status);
    }

    public function transitionTo(PaymentIntentStatus|string $target): self
    {
        $targetEnum = $target instanceof PaymentIntentStatus ? $target : PaymentIntentStatus::from($target);
        $currentEnum = $this->getStatusEnum();

        if (! $currentEnum->canTransitionTo($targetEnum)) {
            throw InvalidPaymentStateException::fromTo($currentEnum, $targetEnum);
        }

        $this->status = $targetEnum;
        $this->save();

        return $this;
    }

    public function isPending(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::PENDING;
    }

    public function isHeld(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::HELD;
    }

    public function isCaptured(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::CAPTURED;
    }

    public function isReleased(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::RELEASED;
    }

    public function isRefunded(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::REFUNDED;
    }

    public function isExpired(): bool
    {
        return $this->getStatusEnum() === PaymentIntentStatus::EXPIRED;
    }
}
