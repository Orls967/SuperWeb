<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AviationRoute extends Model
{
    protected $table = 'avi_routes';

    protected $fillable = [
        'origin_airport_id',
        'destination_airport_id',
        'flight_number',
        'distance_km',
        'scheduled_duration_minutes',
        'base_fare_minor',
        'currency',
        'status',
    ];

    protected $casts = [
        'distance_km' => 'integer',
        'scheduled_duration_minutes' => 'integer',
        'base_fare_minor' => 'integer',
    ];

    public function origin(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'origin_airport_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(AviationAirport::class, 'destination_airport_id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(AviationFlight::class, 'route_id');
    }
}
