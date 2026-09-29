<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Shared\Domain\Traits\HasUuid;

class Booking extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'serve_bookings';

    protected $fillable = [
        'booking_code', 'customer_id', 'mechanic_id', 'service_id',
        'vehicle_id',
        'plate_number', 'vehicle_brand', 'vehicle_model', 'vehicle_year',
        'complaint', 'mechanic_notes', 'booking_date', 'booking_time',
        'status', 'service_cost', 'sparepart_cost', 'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
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
        return $this->belongsTo(\Modules\Core\Domain\Models\Vehicle::class, 'vehicle_id');
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
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
            $code = 'AUTO-' . strtoupper(substr(uniqid(), -6));
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

    public function getStatusEnumAttribute(): \Modules\AutoServe\Domain\Enums\BookingStatus
    {
        return \Modules\AutoServe\Domain\Enums\BookingStatus::fromString($this->attributes['status'] ?? 'pending');
    }

    public function setStatusAttribute($value): void
    {
        $target = $value instanceof \Modules\AutoServe\Domain\Enums\BookingStatus
            ? $value
            : \Modules\AutoServe\Domain\Enums\BookingStatus::tryFromString((string) $value);

        if ($target && isset($this->attributes['status']) && $this->exists) {
            $current = \Modules\AutoServe\Domain\Enums\BookingStatus::tryFromString((string) $this->attributes['status']);
            if ($current && ! $current->canTransitionTo($target) && $current !== $target) {
                throw \Modules\Shared\Domain\Exceptions\InvalidStateTransition::fromTo($current, $target, 'Booking');
            }
        }

        $this->attributes['status'] = $target ? $target->value : (string) $value;
    }

    public function transitionTo(\Modules\AutoServe\Domain\Enums\BookingStatus|string $next): self
    {
        $target = $next instanceof \Modules\AutoServe\Domain\Enums\BookingStatus
            ? $next
            : \Modules\AutoServe\Domain\Enums\BookingStatus::fromString($next);

        $this->status = $target;
        $this->save();

        return $this;
    }

    // --- Status helpers ---
    public function isPending(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::Pending->value; }
    public function isConfirmed(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::Confirmed->value; }
    public function isInProgress(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::InProgress->value; }
    public function isWaitingParts(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::WaitingParts->value; }
    public function isCompleted(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::Completed->value; }
    public function isInvoiced(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::Invoiced->value; }
    public function isCancelled(): bool { return $this->status === \Modules\AutoServe\Domain\Enums\BookingStatus::Cancelled->value; }

    /** Label warna status untuk tampilan badge */
    public function getStatusBadgeAttribute(): string
    {
        $enum = \Modules\AutoServe\Domain\Enums\BookingStatus::tryFromString($this->status);

        return $enum ? $enum->badgeClasses() : 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }
}
