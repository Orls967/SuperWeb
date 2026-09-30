<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mall\Domain\Enums\VehicleType;

class ParkingTariff extends Model
{
    protected $table = 'mall_parking_tariffs';

    protected $fillable = [
        'property_id',
        'vehicle_type',
        'grace_period_minutes',
        'first_hour_rate',
        'subsequent_hour_rate',
        'max_daily_rate',
        'lost_ticket_penalty',
        'is_active',
    ];

    protected $casts = [
        'vehicle_type' => VehicleType::class,
        'grace_period_minutes' => 'integer',
        'first_hour_rate' => 'integer',
        'subsequent_hour_rate' => 'integer',
        'max_daily_rate' => 'integer',
        'lost_ticket_penalty' => 'integer',
        'is_active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
