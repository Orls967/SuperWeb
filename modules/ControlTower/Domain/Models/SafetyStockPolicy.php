<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyStockPolicy extends Model
{
    protected $table = 'sct_safety_stock_policies';

    protected $fillable = [
        'item_code',
        'abc_class',
        'xyz_class',
        'service_level_bps',
        'safety_stock_qty',
        'reorder_point_qty',
        'slow_moving_flag',
        'dead_stock_flag',
        'status',
        'proposed_abc_class',
        'proposed_xyz_class',
        'proposed_service_level_bps',
        'proposed_safety_stock_qty',
        'proposed_reorder_point_qty',
        'echelon_breakdown',
        'proposed_echelon_breakdown',
        'supplier_ref',
        'lead_time_mean_days',
        'lead_time_sigma_days',
        'certificate_digest',
        'approval_id',
        'proposed_by_user_id',
        'decided_by_user_id',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'service_level_bps' => 'integer',
            'safety_stock_qty' => 'integer',
            'reorder_point_qty' => 'integer',
            'slow_moving_flag' => 'boolean',
            'dead_stock_flag' => 'boolean',
            'proposed_service_level_bps' => 'integer',
            'proposed_safety_stock_qty' => 'integer',
            'proposed_reorder_point_qty' => 'integer',
            'echelon_breakdown' => 'array',
            'proposed_echelon_breakdown' => 'array',
            'lead_time_mean_days' => 'integer',
            'lead_time_sigma_days' => 'integer',
            'approval_id' => 'integer',
            'proposed_by_user_id' => 'integer',
            'decided_by_user_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
