<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Resto\Domain\Enums\CateringStatus;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class CateringOrder extends Model implements Payable
{
    use HasUuid;

    protected $table = 'resto_catering_orders';

    protected $fillable = [
        'uuid',
        'number',
        'outlet_id',
        'user_id',
        'package_id',
        'customer_name',
        'customer_phone',
        'event_date',
        'event_time',
        'delivery_address',
        'pax',
        'subtotal',
        'delivery_fee',
        'grand_total',
        'deposit_amount',
        'deposit_intent_id',
        'paid_amount',
        'status',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'pax' => 'integer',
        'subtotal' => 'integer',
        'delivery_fee' => 'integer',
        'grand_total' => 'integer',
        'deposit_amount' => 'integer',
        'paid_amount' => 'integer',
        'status' => CateringStatus::class,
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CateringPackage::class, 'package_id');
    }

    public function depositIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'deposit_intent_id');
    }

    public function remainingAmount(): int
    {
        return max(0, $this->grand_total - $this->paid_amount);
    }

    public function payableAmount(): Money
    {
        return Money::idr($this->deposit_amount ?: $this->grand_total);
    }

    public function payableDescription(): string
    {
        return "Deposit Katering #{$this->number} ({$this->pax} pax)";
    }

    public function payerId(): int
    {
        return (int) ($this->user_id ?? 1);
    }

    public function revenueSplits(): array
    {
        $outletCode = $this->outlet?->code ?: "OUT-{$this->outlet_id}";

        return [
            "revenue:resto:{$outletCode}:catering" => Money::idr($this->deposit_amount ?: $this->grand_total),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->update([
            'status' => CateringStatus::COMPLETED,
            'paid_amount' => $this->grand_total,
        ]);
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->update([
            'status' => CateringStatus::CANCELLED,
            'cancellation_reason' => 'Deposit dilepaskan / refund',
        ]);
    }
}
