<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ControlTowerKpiSnapshot extends Model
{
    protected $table = 'sct_kpi_snapshots';

    protected $fillable = [
        'snapshot_date',
        'otif_bps',
        'cash_to_cash_days',
        'inventory_dos_days',
        'fulfillment_lead_time_hours',
        'logistics_cost_ratio_bps',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'otif_bps' => 'integer',
            'cash_to_cash_days' => 'integer',
            'inventory_dos_days' => 'integer',
            'fulfillment_lead_time_hours' => 'integer',
            'logistics_cost_ratio_bps' => 'integer',
        ];
    }
}
