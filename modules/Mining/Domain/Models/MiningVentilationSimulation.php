<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningVentilationSimulation extends Model
{
    use HasUuids;

    protected $table = 'min_ventilation_simulations';

    protected $fillable = [
        'id',
        'site_id',
        'simulation_code',
        'total_branches',
        'total_loops',
        'iterations_count',
        'iterations_to_converge',
        'converged',
        'is_converged',
        'max_residual_flow',
        'flow_balance_error_m3_s',
        'intake_airflow_m3_s',
        'return_airflow_m3_s',
        'ch4_dilution_capacity_pct',
        'co_concentration_ppm',
        'total_fan_power_kw',
        'fan_operating_point',
        'branch_results',
        'hmac_signature',
        'simulated_by',
        'status',
        'metadata',
    ];

    protected $casts = [
        'total_branches' => 'integer',
        'total_loops' => 'integer',
        'iterations_count' => 'integer',
        'iterations_to_converge' => 'integer',
        'converged' => 'boolean',
        'is_converged' => 'boolean',
        'max_residual_flow' => 'float',
        'flow_balance_error_m3_s' => 'float',
        'intake_airflow_m3_s' => 'float',
        'return_airflow_m3_s' => 'float',
        'ch4_dilution_capacity_pct' => 'float',
        'co_concentration_ppm' => 'float',
        'total_fan_power_kw' => 'float',
        'fan_operating_point' => 'array',
        'branch_results' => 'array',
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
