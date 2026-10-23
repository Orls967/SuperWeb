<?php

declare(strict_types=1);

namespace Modules\Vending\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VendingAutoPo extends Model
{
    protected $table = 'ven_auto_pos';

    protected $fillable = [
        'auto_po_code',
        'outlet_code',
        'item_code',
        'qty_ordered',
        'estimated_total_idr',
        'daily_plafon_idr',
        'status',
        'supplier_id',
    ];

    protected $casts = [
        'qty_ordered' => 'integer',
        'estimated_total_idr' => 'integer',
        'daily_plafon_idr' => 'integer',
    ];
}
