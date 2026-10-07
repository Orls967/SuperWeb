<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceCommandSnapshot extends Model
{
    protected $table = 'min_resource_command_snapshots';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'pit_production_tonnage' => 'float',
        'plant_processing_tonnage' => 'float',
        'terminal_loaded_tonnage' => 'float',
        'live_commodity_price_usd' => 'float',
        'fleet_utilization_rate_pct' => 'float',
        'realized_revenue_minor' => 'integer',
        'total_operating_cost_minor' => 'integer',
        'net_mine_to_market_margin_minor' => 'integer',
    ];
}
