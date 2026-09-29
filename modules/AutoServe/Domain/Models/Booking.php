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

    // --- Status helpers ---
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isInvoiced(): bool { return $this->status === 'invoiced'; }

    /** Label warna status untuk tampilan badge */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-indigo-100 text-indigo-800',
            'completed' => 'bg-green-100 text-green-800',
            'invoiced' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
