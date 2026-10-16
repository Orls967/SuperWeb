<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\C2m;

use Illuminate\Database\Eloquent\Model;

class VmiReplenishment extends Model
{
    protected $table = 'mfg_vmi_replenishments';

    protected $fillable = [
        'vmi_code',
        'supplier_id',
        'sku',
        'current_shelf_stock',
        'reorder_point',
        'suggested_reorder_qty',
        'total_estimated_idr',
        'contract_plafond_idr',
        'status',
        'idempotency_key',
    ];
}
