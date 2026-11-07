<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningVentilationShaft extends Model
{
    use HasUuids;

    protected $table = 'min_ventilation_shafts';

    protected $fillable = [
        'id',
        'site_id',
        'stope_id',
        'branch_code',
        'shaft_code',
        'name',
        'shaft_type',
        'from_node',
        'to_node',
        'resistance_r',
        'initial_flow_q',
        'depth_meters',
        'diameter_meters',
        'length_m',
        'cross_section_area_m2',
        'friction_factor_k',
        'fan_installed_power_kw',
        'fan_rated_airflow_m3_s',
        'fan_operating_pressure_pa',
        'fan_pressure_pa',
        'level_connected',
        'is_fan_branch',
        'is_exhaust_shaft',
        'status',
        'metadata',
    ];

    protected $casts = [
        'resistance_r' => 'float',
        'initial_flow_q' => 'float',
        'depth_meters' => 'float',
        'diameter_meters' => 'float',
        'length_m' => 'float',
        'cross_section_area_m2' => 'float',
        'friction_factor_k' => 'float',
        'fan_installed_power_kw' => 'float',
        'fan_rated_airflow_m3_s' => 'float',
        'fan_operating_pressure_pa' => 'float',
        'fan_pressure_pa' => 'float',
        'is_fan_branch' => 'boolean',
        'is_exhaust_shaft' => 'boolean',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function stope(): BelongsTo
    {
        return $this->belongsTo(MiningUndergroundStope::class, 'stope_id');
    }
}
