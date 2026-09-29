<?php

declare(strict_types=1);

namespace Modules\AutoDex\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Modules\Shared\Domain\Traits\HasUuid;

class Car extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'dex_cars';

    protected $fillable = [
        'brand_id', 'model', 'slug', 'year_start', 'year_end',
        'body_type', 'fuel_type', 'engine', 'horsepower', 'torque_nm',
        'transmission', 'drivetrain', 'top_speed_kmh', 'zero_to_100',
        'range_km', 'battery_kwh', 'price_idr', 'image_url',
        'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_idr' => 'decimal:2',
            'zero_to_100' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Car $car) {
            if (empty($car->slug)) {
                $brand = Brand::find($car->brand_id);
                $base = Str::slug(($brand?->name ?? '') . ' ' . $car->model . ' ' . $car->year_start);
                $car->slug = $base;
            }
        });
    }

    // --- Relationships ---

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /** Users yang punya mobil ini di garasi mereka */
    public function garageUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'core_vehicles', 'car_id', 'user_id')
            ->wherePivotNull('deleted_at')
            ->withPivot(['id', 'uuid', 'plate_number', 'color', 'vin', 'odometer_km', 'status'])
            ->withTimestamps();
    }

    /** Users yang meng-wishlist mobil ini */
    public function wishlistUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dex_wishlists')
            ->withPivot(['priority', 'notes'])
            ->withTimestamps();
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeByFuelType($query, $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }

    public function scopeByBodyType($query, $bodyType)
    {
        return $query->where('body_type', $bodyType);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('model', 'LIKE', "%{$term}%")
              ->orWhereHas('brand', fn ($b) => $b->where('name', 'LIKE', "%{$term}%"));
        });
    }

    // --- Helpers ---

    /** Full display name: "Toyota Supra (2019)" */
    public function getFullNameAttribute(): string
    {
        $year = $this->year_end ? "{$this->year_start}-{$this->year_end}" : "{$this->year_start}+";

        return "{$this->brand->name} {$this->model} ({$year})";
    }

    /** Format harga IDR */
    public function getFormattedPriceAttribute(): string
    {
        if (! $this->price_idr) {
            return 'N/A';
        }

        if ($this->price_idr >= 1_000_000_000) {
            return 'Rp ' . number_format($this->price_idr / 1_000_000_000, 1, ',', '.') . ' M';
        }

        return 'Rp ' . number_format($this->price_idr / 1_000_000, 0, ',', '.') . ' Jt';
    }

    /** Fuel type badge color */
    public function getFuelBadgeAttribute(): string
    {
        return match($this->fuel_type) {
            'electric' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'hybrid' => 'bg-teal-500/10 text-teal-400 border-teal-500/20',
            'diesel' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            'hydrogen' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
            default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
        };
    }

    /** Cek apakah mobil ini EV */
    public function isElectric(): bool
    {
        return $this->fuel_type === 'electric';
    }
}
