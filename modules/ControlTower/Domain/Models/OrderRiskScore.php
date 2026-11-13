<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRiskScore extends Model
{
    protected $table = 'sct_order_risk_scores';

    protected $fillable = [
        'item_code',
        'order_reference',
        'risk_score',
        'risk_tier',
        'shortfall_component',
        'lead_time_component',
        'shelf_life_component',
        'transit_component',
        'factors_json',
        'mitigation_recommendation',
        'scored_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'shortfall_component' => 'integer',
        'lead_time_component' => 'integer',
        'shelf_life_component' => 'integer',
        'transit_component' => 'integer',
        'factors_json' => 'array',
        'scored_at' => 'datetime',
    ];
}
