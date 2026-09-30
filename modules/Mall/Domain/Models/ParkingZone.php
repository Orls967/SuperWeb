<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Shared\Domain\Traits\HasUuid;

class ParkingZone extends Model
{
    use HasUuid;

    protected $table = 'mall_parking_zones';

    protected $fillable = [
        'uuid',
        'property_id',
        'code',
        'name',
        'vehicle_type',
        'total_capacity',
        'current_occupancy',
        'is_active',
    ];

    protected $casts = [
        'vehicle_type' => VehicleType::class,
        'total_capacity' => 'integer',
        'current_occupancy' => 'integer',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'parking_zone_id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ParkingSession::class, 'parking_zone_id')
            ->where('status', ParkingSessionStatus::ACTIVE);
    }

    public function isFull(): bool
    {
        return $this->current_occupancy >= $this->total_capacity;
    }

    public function availableSlots(): int
    {
        return max(0, $this->total_capacity - $this->current_occupancy);
    }

    public function occupancyRate(): float
    {
        if ($this->total_capacity <= 0) {
            return 0.0;
        }

        return round(($this->current_occupancy / $this->total_capacity) * 100, 1);
    }
}
