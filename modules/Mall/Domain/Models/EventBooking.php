<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\EventBookingStatus;
use Modules\Mall\Domain\Enums\EventType;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class EventBooking extends Model implements Payable
{
    use HasUuid;

    protected $table = 'mall_event_bookings';

    protected $fillable = [
        'uuid',
        'property_id',
        'event_space_id',
        'customer_id',
        'tenant_id',
        'booking_number',
        'event_name',
        'event_type',
        'start_date',
        'end_date',
        'booth_count',
        'total_amount',
        'paid_amount',
        'status',
        'notes',
        'confirmed_at',
    ];

    protected $casts = [
        'event_type' => EventType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'booth_count' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'status' => EventBookingStatus::class,
        'confirmed_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(EventSpace::class, 'event_space_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // --- Payable Contract Implementation ---

    public function payableAmount(): Money
    {
        return Money::idr($this->total_amount);
    }

    public function payableDescription(): string
    {
        return "Sewa Event Atrium {$this->booking_number} ({$this->event_name})";
    }

    public function payerId(): int
    {
        return (int) $this->customer_id;
    }

    public function revenueSplits(): array
    {
        return [
            'revenue:mall:event:IDR' => Money::idr($this->total_amount),
        ];
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->update([
            'paid_amount' => $this->total_amount,
            'status' => EventBookingStatus::CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->update([
            'status' => EventBookingStatus::CANCELLED,
        ]);
    }
}
