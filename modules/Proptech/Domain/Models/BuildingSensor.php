<?php

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingSensor extends Model
{
    protected $table = 'prp_building_sensors';

    protected $guarded = [];

    protected $casts = [
        'recorded_at' => 'datetime',
        'reading_value' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
