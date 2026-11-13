<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SopCycle extends Model
{
    protected $table = 'sct_sop_cycles';

    protected $fillable = [
        'cycle_code',
        'period',
        'status',
        'scenario_type',
        'demand_plan_qty',
        'supply_plan_qty',
        'capacity_utilization_bps',
        'gross_margin_est_idr',
        'cash_impact_est_idr',
        'signed_off_by_user_id',
        'signed_off_at',
    ];

    protected function casts(): array
    {
        return [
            'demand_plan_qty' => 'integer',
            'supply_plan_qty' => 'integer',
            'capacity_utilization_bps' => 'integer',
            'gross_margin_est_idr' => 'integer',
            'cash_impact_est_idr' => 'integer',
            'signed_off_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SopEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SopEvent::class, 'sop_cycle_id');
    }
}
