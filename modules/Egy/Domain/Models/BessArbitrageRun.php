<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BessArbitrageRun extends Model
{
    protected $table = 'egy_bess_arbitrage_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'energy_discharged_kwh' => 'float',
        'charging_cost_minor' => 'integer',
        'discharging_revenue_minor' => 'integer',
        'net_arbitrage_profit_minor' => 'integer',
        'battery_cycle_count_increment' => 'integer',
        'state_of_health_pct' => 'float',
    ];
}
