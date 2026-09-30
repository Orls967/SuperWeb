<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\FleetStatus;

class Aircraft extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_aircraft';

    protected $fillable = [
        'registration',
        'type',
        'max_payload_kg',
        'uld_positions',
        'status',
        'current_location_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => FleetStatus::class,
            'max_payload_kg' => 'integer',
            'uld_positions' => 'integer',
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
