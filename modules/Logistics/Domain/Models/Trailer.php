<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\TrailerType;

class Trailer extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_trailers';

    protected $fillable = [
        'code',
        'type',
        'payload_kg',
        'status',
        'current_location_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrailerType::class,
            'status' => FleetStatus::class,
            'payload_kg' => 'integer',
        ];
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
