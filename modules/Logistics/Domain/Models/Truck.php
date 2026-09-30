<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\TruckType;

class Truck extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_trucks';

    protected $fillable = [
        'vehicle_id',
        'plate_number',
        'type',
        'payload_kg',
        'volume_dm3',
        'required_license',
        'service_interval_m',
        'odometer_m',
        'status',
        'current_location_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TruckType::class,
            'status' => FleetStatus::class,
            'payload_kg' => 'integer',
            'volume_dm3' => 'integer',
            'service_interval_m' => 'integer',
            'odometer_m' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function canTransitionTo(FleetStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }
}
