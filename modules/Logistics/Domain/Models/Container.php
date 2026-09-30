<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\ValueObjects\Iso6346Validator;

class Container extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_containers';

    protected $fillable = [
        'container_number',
        'size_type',
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

    protected static function booted(): void
    {
        static::saving(function (Container $container) {
            $container->container_number = Iso6346Validator::validate($container->container_number);
        });
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
