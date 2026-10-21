<?php

declare(strict_types=1);

namespace Modules\Esg\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EsgCarbonLog extends Model
{
    protected $table = 'esg_carbon_logs';

    protected $fillable = [
        'domain_code',
        'scope_level', // SCOPE_1, SCOPE_2, SCOPE_3
        'activity_name',
        'activity_volume',
        'unit_code',
        'emission_factor_bps',
        'co2_emitted_kg',
        'reporting_period',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'activity_volume' => 'integer',
        'emission_factor_bps' => 'integer',
        'co2_emitted_kg' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
