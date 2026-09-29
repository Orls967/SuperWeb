<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Enums\EstimateStatus;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Payment\Contracts\Payable;
use Modules\Payment\Domain\Models\PaymentIntent;
use Modules\Shared\Domain\Exceptions\InvalidStateTransition;
use Modules\Shared\Domain\Traits\HasUuid;
use Modules\Shared\Domain\ValueObjects\Money;

class Booking extends Model implements Payable
{
    use HasFactory, HasUuid;

    protected $table = 'serve_bookings';

    protected $fillable = [
        'booking_code', 'customer_id', 'mechanic_id', 'service_id',
        'vehicle_id',
        'plate_number', 'vehicle_brand', 'vehicle_model', 'vehicle_year',
        'complaint', 'mechanic_notes', 'booking_date', 'booking_time',
        'status', 'service_cost', 'sparepart_cost', 'grand_total',
        'payment_status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'paid_at' => 'datetime',
            'service_cost' => 'decimal:2',
            'sparepart_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    // --- Relationships ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /** Semua estimasi perbaikan pada booking ini */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'booking_id')->latest('id');
    }

    /** Estimasi yang sudah disetujui customer (dananya ditahan di escrow) */
    public function approvedEstimate(): ?Estimate
    {
        return $this->estimates()
            ->where('status', EstimateStatus::Approved->value)
            ->first();
    }

    /** Spareparts yang digunakan (many-to-many via pivot) */
    public function spareparts(): BelongsToMany
    {
        return $this->belongsToMany(Sparepart::class, 'serve_booking_sparepart')
            ->withPivot(['quantity', 'unit_price', 'subtotal'])
            ->withTimestamps();
    }

    // --- Helpers ---

    /** Generate kode booking unik: AUTO-XXXXXX */
    public static function generateBookingCode(): string
    {
        do {
            $code = 'AUTO-'.strtoupper(substr(uniqid(), -6));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    /** Hitung ulang total biaya dari jasa + sparepart */
    public function recalculateCosts(): void
    {
        $this->service_cost = $this->service->price ?? 0;
        $this->sparepart_cost = $this->spareparts->sum('pivot.subtotal');
        $this->grand_total = $this->service_cost + $this->sparepart_cost;
        $this->save();
    }

    // --- Status & State Machine ---

    public function getStatusEnumAttribute(): BookingStatus
    {
        return BookingStatus::fromString($this->attributes['status'] ?? 'pending');
    }

    public function setStatusAttribute($value): void
    {
        $target = $value instanceof BookingStatus
            ? $value
            : BookingStatus::tryFromString((string) $value);

        if ($target && isset($this->attributes['status']) && $this->exists) {
            $current = BookingStatus::tryFromString((string) $this->attributes['status']);
            if ($current && ! $current->canTransitionTo($target) && $current !== $target) {
                throw InvalidStateTransition::fromTo($current, $target, 'Booking');
            }
        }

        $this->attributes['status'] = $target ? $target->value : (string) $value;
    }

    public function transitionTo(BookingStatus|string $next): self
    {
        $target = $next instanceof BookingStatus
            ? $next
            : BookingStatus::fromString($next);

        $this->status = $target;
        $this->save();

        return $this;
    }

    // --- Status helpers ---
    public function isPending(): bool
    {
        return $this->status === BookingStatus::Pending->value;
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::Confirmed->value;
    }

    public function isInProgress(): bool
    {
        return $this->status === BookingStatus::InProgress->value;
    }

    public function isWaitingParts(): bool
    {
        return $this->status === BookingStatus::WaitingParts->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === BookingStatus::Completed->value;
    }

    public function isInvoiced(): bool
    {
        return $this->status === BookingStatus::Invoiced->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === BookingStatus::Cancelled->value;
    }

    /** Label warna status untuk tampilan badge */
    public function getStatusBadgeAttribute(): string
    {
        $enum = BookingStatus::tryFromString($this->status);

        return $enum ? $enum->badgeClasses() : 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }

    // --- Payable Contract Implementation ---

    public function payableAmount(): Money
    {
        return Money::IDR($this->grand_total ?: 0);
    }

    public function payableDescription(): string
    {
        return "Pembayaran Servis {$this->booking_code} ({$this->vehicle_brand} {$this->vehicle_model})";
    }

    public function payerId(): int
    {
        return (int) $this->customer_id;
    }

    public function revenueSplits(): array
    {
        $splits = [];
        $serviceCost = (float) ($this->service_cost ?: 0);
        $sparepartCost = (float) ($this->sparepart_cost ?: 0);
        $grandTotal = (float) ($this->grand_total ?: 0);

        if ($serviceCost > 0) {
            $splits['revenue:autoserve:service:IDR'] = Money::IDR($serviceCost);
        }

        if ($sparepartCost > 0) {
            $splits['revenue:autoserve:parts:IDR'] = Money::IDR($sparepartCost);
        }

        if (empty($splits) && $grandTotal > 0) {
            $splits['revenue:autoserve:service:IDR'] = Money::IDR($grandTotal);
        }

        return $splits;
    }

    public function onPaymentCaptured(PaymentIntent $intent): void
    {
        $this->payment_status = 'paid';
        $this->paid_at = now();

        if ($this->status === BookingStatus::Completed->value) {
            $this->status = BookingStatus::Invoiced->value;
        }

        $this->save();
    }

    public function onPaymentRefunded(PaymentIntent $intent): void
    {
        $this->payment_status = 'refunded';
        $this->save();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid' || $this->payment_status === null;
    }

    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    public function paymentIntents(): MorphMany
    {
        return $this->morphMany(PaymentIntent::class, 'payable');
    }

    public function latestPaymentIntent(): MorphOne
    {
        return $this->morphOne(PaymentIntent::class, 'payable')->latestOfMany();
    }
}
