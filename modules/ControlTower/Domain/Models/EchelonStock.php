<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EchelonStock extends Model
{
    protected $table = 'sct_echelon_stocks';

    protected $fillable = [
        'item_code',
        'item_name',
        'echelon_node',
        'on_hand_qty',
        'in_transit_qty',
        'reserved_qty',
        'safety_stock_qty',
        'abc_class',
        'xyz_class',
    ];

    protected $casts = [
        'on_hand_qty' => 'integer',
        'in_transit_qty' => 'integer',
        'reserved_qty' => 'integer',
        'safety_stock_qty' => 'integer',
    ];
}
