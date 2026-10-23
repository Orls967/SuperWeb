<?php

declare(strict_types=1);

namespace Modules\AutoServe\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\AutoServe\Contracts\Sparepart as BaseSparepart;
use Modules\Shared\Domain\Traits\HasUuid;

class Sparepart extends BaseSparepart
{
    use HasFactory, HasUuid;

    protected $table = 'serve_spareparts';

    protected $fillable = ['name', 'code', 'stock', 'price', 'unit', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Relasi many-to-many ke bookings via pivot */
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'serve_booking_sparepart')
            ->withPivot(['quantity', 'unit_price', 'subtotal'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Cek apakah stok mencukupi */
    public function hasStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }
}
