<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TwinScenario extends Model
{
    protected $table = 'sct_twin_scenarios';

    protected $fillable = [
        'scenario_code',
        'name',
        'shock_type',
        'shock_magnitude_bps',
        'duration_days',
        'baseline_otif_bps',
        'simulated_otif_bps',
        'at_risk_orders_count',
        'estimated_margin_delta_idr',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'shock_magnitude_bps' => 'integer',
            'duration_days' => 'integer',
            'baseline_otif_bps' => 'integer',
            'simulated_otif_bps' => 'integer',
            'at_risk_orders_count' => 'integer',
            'estimated_margin_delta_idr' => 'integer',
        ];
    }
}
