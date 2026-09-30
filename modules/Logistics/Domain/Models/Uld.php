<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\FleetStatus;

class Uld extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_ulds';

    protected $fillable = [
        'uld_code',
        'type',
        'tare_kg',
        'max_gross_kg',
        'status',
        'current_location_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => FleetStatus::class,
            'tare_kg' => 'integer',
            'max_gross_kg' => 'integer',
        ];
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function payloadCapacityKg(): int
    {
        return max(0, $this->max_gross_kg - $this->tare_kg);
    }

    public function canTransitionTo(FleetStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }
}
