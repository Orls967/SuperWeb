<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\ValueObjects\ImoNumberValidator;

class Vessel extends LogisticsEntity
{
    use HasFactory;

    protected $table = 'lgx_vessels';

    protected $fillable = [
        'imo_number',
        'name',
        'flag',
        'teu_capacity',
        'reefer_plugs',
        'dwt_tonnes',
        'status',
        'current_location_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => FleetStatus::class,
            'teu_capacity' => 'integer',
            'reefer_plugs' => 'integer',
            'dwt_tonnes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vessel $vessel) {
            $vessel->imo_number = ImoNumberValidator::validate($vessel->imo_number);
        });
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
