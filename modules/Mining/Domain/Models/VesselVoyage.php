<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VesselVoyage extends Model
{
    protected $table = 'min_vessel_voyages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'voyage_number',
        'vessel_name',
        'buyer_party_id',
        'destination_country',
        'contracted_tonnage',
        'loaded_tonnage',
        'bill_of_lading_hash',
        'allowed_laytime_hours',
        'actual_laytime_hours',
        'demurrage_rate_per_hour_minor',
        'demurrage_total_minor',
        'sanctions_blocked',
        'status',
    ];

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
