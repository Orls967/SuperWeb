<?php

namespace Modules\Esg\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EsgEmission extends Model
{
    use HasUuids;

    protected $table = 'esg_emissions';

    protected $fillable = [
        'emission_number',
        'entity_id',
        'scope',
        'activity_type',
        'activity_data_amount',
        'activity_uom',
        'emission_factor',
        'co2e_kg',
        'reporting_period',
        'source_module',
        'source_reference',
        'metadata',
    ];

    protected $casts = [
        'activity_data_amount' => 'decimal:4',
        'emission_factor' => 'decimal:6',
        'co2e_kg' => 'decimal:4',
        'reporting_period' => 'date',
        'metadata' => 'array',
    ];
}
