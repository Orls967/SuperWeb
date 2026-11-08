<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FleetClass extends Model
{
    protected $table = 'prt_fleet_classes';

    protected $fillable = [
        'code',
        'society_name',
        'class_notation',
        'ice_class',
        'last_special_survey',
        'next_special_survey',
        'last_drydock_survey',
        'next_drydock_survey',
        'status',
    ];

    protected $casts = [
        'last_special_survey' => 'date',
        'next_special_survey' => 'date',
        'last_drydock_survey' => 'date',
        'next_drydock_survey' => 'date',
    ];

    public function vessels(): HasMany
    {
        return $this->hasMany(FleetVessel::class, 'fleet_class_id');
    }
}

