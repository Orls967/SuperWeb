<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTier extends Model
{
    protected $table = 'agy_agent_tiers';

    protected $fillable = [
        'code', 'name', 'min_sales_count', 'min_volume_idr', 'bonus_multiplier', 'perks',
    ];

    protected $casts = [
        'min_sales_count' => 'integer',
        'min_volume_idr' => 'integer',
        'bonus_multiplier' => 'float',
        'perks' => 'array',
    ];
}
