<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HtlAwardNightPricingRule extends Model
{
    use HasUuids;

    protected $table = 'htl_award_night_pricing_rules';

    protected $fillable = [
        'id',
        'property_reference',
        'room_type_reference',
        'base_points_required',
        'marginal_cost_idr',
        'floor_multiplier',
        'seasonality_multiplier',
        'is_active',
    ];

    protected $casts = [
        'base_points_required' => 'integer',
        'marginal_cost_idr' => 'integer',
        'floor_multiplier' => 'float',
        'seasonality_multiplier' => 'float',
        'is_active' => 'boolean',
    ];
}
