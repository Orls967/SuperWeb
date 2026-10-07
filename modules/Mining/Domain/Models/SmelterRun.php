<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SmelterRun extends Model
{
    protected $table = 'min_smelter_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'input_ore_tonnage' => 'float',
        'input_grade_pct' => 'float',
        'output_metal_tonnage' => 'float',
        'recovery_rate_pct' => 'float',
        'byproduct_slag_tonnage' => 'float',
        'byproduct_revenue_idr' => 'integer',
        'energy_kwh_per_ton' => 'float',
    ];
}
