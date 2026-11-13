<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot kesehatan persediaan multi-echelon dan eksposur modal kerja tertahan (X53-3).
 */
class InventoryHealthSnapshot extends Model
{
    protected $table = 'sct_inventory_health_snapshots';

    protected $fillable = [
        'item_code',
        'echelon_node',
        'health_status',
        'aging_bucket',
        'on_hand_qty',
        'quarantine_qty',
        'available_qty',
        'daily_demand_qty',
        'dos_days',
        'oldest_lot_age_days',
        'tied_capital_idr',
        'obsolescence_risk_idr',
        'snapshot_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand_qty' => 'integer',
            'quarantine_qty' => 'integer',
            'available_qty' => 'integer',
            'daily_demand_qty' => 'integer',
            'dos_days' => 'integer',
            'oldest_lot_age_days' => 'integer',
            'tied_capital_idr' => 'integer',
            'obsolescence_risk_idr' => 'integer',
            'snapshot_date' => 'date',
        ];
    }
}
