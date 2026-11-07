<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VenueExternalFactor extends Model
{
    protected $table = 'ven_external_factors';

    protected $fillable = [
        'factor_key',
        'version',
        'effective_from',
        'source',
        'label',
        'numeric_value_scaled',
        'unit',
        'metadata_json',
    ];

    protected $casts = [
        'version' => 'integer',
        'effective_from' => 'date',
        'numeric_value_scaled' => 'integer',
        'metadata_json' => 'array',
    ];
}
