<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VesselVoyage extends Model
{
    protected $table = 'min_vessel_voyages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'contracted_tonnage' => 'float',
        'loaded_tonnage' => 'float',
        'allowed_laytime_hours' => 'float',
        'actual_laytime_hours' => 'float',
        'demurrage_rate_per_hour_minor' => 'integer',
        'demurrage_total_minor' => 'integer',
        'sanctions_blocked' => 'boolean',
    ];
}
