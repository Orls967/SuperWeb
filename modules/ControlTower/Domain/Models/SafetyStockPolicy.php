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
    ];

    protected function casts(): array
    {
        return [
            'service_level_bps' => 'integer',
            'safety_stock_qty' => 'integer',
            'reorder_point_qty' => 'integer',
            'slow_moving_flag' => 'boolean',
            'dead_stock_flag' => 'boolean',
        ];
    }
}
