<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\TransportMode;

class Lane extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_lanes';

    protected $fillable = [
        'origin_id',
        'destination_id',
        'mode',
        'distance_m',
        'standard_transit_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mode' => TransportMode::class,
            'distance_m' => 'integer',
            'standard_transit_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    public function distanceKm(): float
    {
        return round($this->distance_m / 1000, 2);
    }

    public function standardTransitHours(): float
    {
        return round($this->standard_transit_minutes / 60, 1);
    }
}
