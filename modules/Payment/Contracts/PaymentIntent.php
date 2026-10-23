<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;

/**
 * Cross-module contract for PaymentIntent model.
 */
class PaymentIntent extends Model
{
    protected $table = 'pay_payment_intents';

    protected $casts = [
        'status' => PaymentIntentStatus::class,
    ];

    public function getStatusEnum(): PaymentIntentStatus
    {
        return $this->status instanceof PaymentIntentStatus
            ? $this->status
            : PaymentIntentStatus::from((string) $this->status);
    }

    public function transitionTo(PaymentIntentStatus|string $target): self
    {
        $targetEnum = $target instanceof PaymentIntentStatus ? $target : PaymentIntentStatus::from($target);
        $currentEnum = $this->getStatusEnum();

        if (! $currentEnum->canTransitionTo($targetEnum)) {
            throw new \DomainException("Cannot transition payment intent from {$currentEnum->value} to {$targetEnum->value}");
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
}
